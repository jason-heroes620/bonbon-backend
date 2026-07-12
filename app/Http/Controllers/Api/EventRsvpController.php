<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EventPricingRule;
use App\Models\EventQuestionOption;
use App\Models\EventQuestionnaire;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAnswer;
use App\Models\Events;
use App\Models\MembershipTypes;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventRsvpController extends Controller
{
    public function showRegistration(Request $request, string $event_id)
    {
        $event = Events::query()->whereKey($event_id)->first();
        if (!$event || !$event->is_active || !$event->is_published) {
            return response()->json([
                'message' => 'Event not found.',
            ], 404);
        }

        $registration = EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->where('user_id', (string) $request->user()->user_id)
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'Registration not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'event_registration' => $this->formatRegistrationSummary($registration),
            ],
        ]);
    }

    public function questionnaires(string $event_id)
    {
        $event = Events::query()->whereKey($event_id)->first();
        if (!$event || !$event->is_active || !$event->is_published) {
            return response()->json([
                'message' => 'Event not found.',
            ], 404);
        }

        $questions = EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->where('is_active', true)
            ->with(['options' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => [
                'event' => $event,
                'questions' => $questions,
            ],
        ]);
    }

    public function start(Request $request, string $event_id)
    {
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $userId = (string) $request->user()->user_id;
        $quantity = (int) ($validated['quantity'] ?? 1);

        return DB::transaction(function () use ($event_id, $userId, $quantity) {
            $event = Events::query()->whereKey($event_id)->lockForUpdate()->first();
            if (!$event || !$event->is_active || !$event->is_published) {
                return response()->json([
                    'message' => 'Event not found.',
                ], 404);
            }

            $this->assertEventRsvpWindow($event);

            $existing = EventRegistration::query()
                ->where('event_id', $event->event_id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing && (string) $existing->registration_status === 'confirmed') {
                return response()->json([
                    'message' => 'You have already joined this event.',
                    'data' => [
                        'event_registration_id' => $existing->event_registration_id,
                        'registration_status' => $existing->registration_status,
                    ],
                ], 422);
            }

            $membershipType = $this->getUserMembershipType($userId);
            $membershipTypeId = $this->getMembershipTypeIdByName($membershipType);
            $pricing = $this->resolveEventPricing($event, $membershipTypeId, $quantity);

            $cartItemId = null;
            $seatHoldExpiresAt = null;

            $shouldRestart = false;
            if ($existing && $existing->seat_hold_expires_at && now()->greaterThan($existing->seat_hold_expires_at)) {
                $existing->update([
                    'registration_status' => 'expired',
                    'expired_at' => now(),
                ]);
                $existing = $existing->fresh();
                $shouldRestart = true;
            }

            if (!$event->is_unlimited_seats) {
                $excludedRegistrationId = $existing ? (string) $existing->event_registration_id : null;
                $this->assertSeatAvailable($event, $quantity, $excludedRegistrationId);
            }

            if ($event->registration_type === 'paid') {
                $cart = $this->getOrCreateActiveCart($userId);
                if ($existing && in_array((string) $existing->registration_status, ['draft', 'pending_payment'], true) && !$shouldRestart) {
                    $seatHoldExpiresAt = $existing->seat_hold_expires_at;
                } else {
                    $seatHoldExpiresAt = !$event->is_unlimited_seats ? now()->addMinutes((int) $event->seat_hold_minutes) : null;
                }
                if ($seatHoldExpiresAt) {
                    $cart->update([
                        'expires_at' => $seatHoldExpiresAt,
                    ]);
                }

                $cartItem = CartItem::create([
                    'cart_id' => $cart->cart_id,
                    'line_type' => 'event',
                    'source_id' => (string) $event->event_id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $pricing['unit_price'],
                    'discount' => (float) $pricing['discount_amount'],
                    'tax' => 0.0,
                    'total_price' => (float) $pricing['total_price'],
                    'metadata_json' => [
                        'uom' => 'ticket',
                    ],
                ]);
                $cartItemId = (string) $cartItem->cart_item_id;
            } else {
                if ($existing && in_array((string) $existing->registration_status, ['draft', 'pending_payment'], true) && !$shouldRestart) {
                    $seatHoldExpiresAt = $existing->seat_hold_expires_at;
                } else {
                    $seatHoldExpiresAt = !$event->is_unlimited_seats ? now()->addMinutes((int) $event->seat_hold_minutes) : null;
                }
            }

            $status = $event->registration_type === 'free' && !$event->require_questionnaire ? 'confirmed' : 'draft';

            if ($existing && in_array((string) $existing->registration_status, ['cancelled', 'expired'], true)) {
                EventRegistrationAnswer::query()
                    ->where('event_registration_id', $existing->event_registration_id)
                    ->delete();

                $existing->update([
                    'cart_item_id' => $cartItemId,
                    'order_id' => null,
                    'payment_id' => null,
                    'registration_status' => $status,
                    'seat_hold_expires_at' => $seatHoldExpiresAt,
                    'membership_type_at_registration' => $membershipType,
                    'quantity' => $quantity,
                    'price_before_discount' => (float) $pricing['price_before_discount'],
                    'discount_amount' => (float) $pricing['discount_amount'],
                    'price_paid' => (float) $pricing['total_price'],
                    'joined_at' => now(),
                    'confirmed_at' => $status === 'confirmed' ? now() : null,
                    'expired_at' => null,
                ]);

                $registration = $existing->fresh();
            } elseif ($existing && in_array((string) $existing->registration_status, ['draft', 'pending_payment'], true) && !$shouldRestart) {
                $existing->update([
                    'cart_item_id' => $cartItemId ?: $existing->cart_item_id,
                    'seat_hold_expires_at' => $seatHoldExpiresAt,
                    'membership_type_at_registration' => $membershipType,
                    'quantity' => $quantity,
                    'price_before_discount' => (float) $pricing['price_before_discount'],
                    'discount_amount' => (float) $pricing['discount_amount'],
                    'price_paid' => (float) $pricing['total_price'],
                    'joined_at' => now(),
                ]);

                $registration = $existing->fresh();
            } else {
                $registration = EventRegistration::create([
                    'event_id' => $event->event_id,
                    'user_id' => $userId,
                    'cart_item_id' => $cartItemId,
                    'order_id' => null,
                    'payment_id' => null,
                    'registration_status' => $status,
                    'seat_hold_expires_at' => $seatHoldExpiresAt,
                    'membership_type_at_registration' => $membershipType,
                    'quantity' => $quantity,
                    'price_before_discount' => (float) $pricing['price_before_discount'],
                    'discount_amount' => (float) $pricing['discount_amount'],
                    'price_paid' => (float) $pricing['total_price'],
                    'joined_at' => now(),
                    'confirmed_at' => $status === 'confirmed' ? now() : null,
                    'expired_at' => null,
                ]);
            }

            if ($cartItemId) {
                CartItem::query()->whereKey($cartItemId)->update([
                    'metadata_json' => [
                        'uom' => 'ticket',
                        'event_registration_id' => (string) $registration->event_registration_id,
                    ],
                ]);
            }

            $questions = EventQuestionnaire::query()
                ->where('event_id', $event->event_id)
                ->where('is_active', true)
                ->with(['options' => function ($q) {
                    $q->where('is_active', true)->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'data' => [
                    'event' => $event,
                    'event_registration' => $registration,
                    'pricing' => $pricing,
                    'questions' => $questions,
                ],
            ], $existing ? 200 : 201);
        });
    }

    public function submitAnswers(Request $request, string $event_id)
    {
        $validated = $request->validate([
            'event_registration_id' => ['required', 'uuid'],
            'answers' => ['required', 'array'],
            'answers.*.event_questionnaire_id' => ['required', 'uuid'],
            'answers.*.answer_text' => ['nullable', 'string'],
            'answers.*.answer_value' => ['nullable', 'string', 'max:255'],
            'answers.*.answer_values' => ['nullable', 'array'],
            'answers.*.answer_values.*' => ['string', 'max:255'],
        ]);

        $userId = (string) $request->user()->user_id;
        $event = Events::query()->whereKey($event_id)->first();
        if (!$event || !$event->is_active || !$event->is_published) {
            return response()->json([
                'message' => 'Event not found.',
            ], 404);
        }

        $registration = EventRegistration::query()
            ->whereKey((string) $validated['event_registration_id'])
            ->where('event_id', $event->event_id)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'Registration not found.',
            ], 404);
        }

        if (in_array((string) $registration->registration_status, ['expired', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Registration is no longer active.',
            ], 422);
        }

        if ($registration->seat_hold_expires_at && now()->greaterThan($registration->seat_hold_expires_at)) {
            $registration->update([
                'registration_status' => 'expired',
                'expired_at' => now(),
            ]);
            return response()->json([
                'message' => 'Seat hold expired.',
            ], 422);
        }

        $questions = EventQuestionnaire::query()
            ->where('event_id', $event->event_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('event_questionnaire_id');

        $optionMap = EventQuestionOption::query()
            ->whereIn(
                'event_questionnaire_id',
                $questions->keys()->all(),
            )
            ->where('is_active', true)
            ->get()
            ->groupBy('event_questionnaire_id');

        DB::transaction(function () use ($registration, $validated, $questions, $optionMap) {
            EventRegistrationAnswer::query()
                ->where('event_registration_id', $registration->event_registration_id)
                ->delete();

            foreach ($validated['answers'] as $a) {
                $qid = (string) $a['event_questionnaire_id'];
                $question = $questions->get($qid);
                if (!$question) {
                    continue;
                }

                $type = (string) $question->question_type_snapshot;
                $answerText = isset($a['answer_text']) ? (string) $a['answer_text'] : null;
                $answerValue = isset($a['answer_value']) ? (string) $a['answer_value'] : null;
                $answerValues = isset($a['answer_values']) && is_array($a['answer_values']) ? $a['answer_values'] : null;

                if (in_array($type, ['short_text', 'long_text'], true)) {
                    if ($answerText === null || trim($answerText) === '') {
                        continue;
                    }
                    EventRegistrationAnswer::create([
                        'event_registration_id' => $registration->event_registration_id,
                        'event_questionnaire_id' => $qid,
                        'event_question_option_id' => null,
                        'answer_text' => $answerText,
                        'answer_value' => null,
                    ]);
                    continue;
                }

                if (in_array($type, ['yes_no', 'single_select'], true)) {
                    if ($answerValue === null || trim($answerValue) === '') {
                        continue;
                    }

                    $optionId = null;
                    $options = $optionMap->get($qid) ?? collect();
                    $match = $options->firstWhere('option_value', $answerValue);
                    if ($match) {
                        $optionId = $match->event_question_option_id;
                    }

                    EventRegistrationAnswer::create([
                        'event_registration_id' => $registration->event_registration_id,
                        'event_questionnaire_id' => $qid,
                        'event_question_option_id' => $optionId,
                        'answer_text' => null,
                        'answer_value' => $answerValue,
                    ]);
                    continue;
                }

                if ($type === 'multi_select') {
                    if (!$answerValues || count($answerValues) === 0) {
                        continue;
                    }
                    foreach ($answerValues as $v) {
                        $v = (string) $v;
                        if (trim($v) === '') {
                            continue;
                        }
                        $optionId = null;
                        $options = $optionMap->get($qid) ?? collect();
                        $match = $options->firstWhere('option_value', $v);
                        if ($match) {
                            $optionId = $match->event_question_option_id;
                        }
                        EventRegistrationAnswer::create([
                            'event_registration_id' => $registration->event_registration_id,
                            'event_questionnaire_id' => $qid,
                            'event_question_option_id' => $optionId,
                            'answer_text' => null,
                            'answer_value' => $v,
                        ]);
                    }
                }
            }
        });

        $nextStatus = $event->registration_type === 'free' ? 'confirmed' : 'pending_payment';
        $registration->update([
            'registration_status' => $nextStatus,
            'confirmed_at' => $nextStatus === 'confirmed' ? now() : $registration->confirmed_at,
        ]);

        return response()->json([
            'data' => [
                'event_registration_id' => $registration->event_registration_id,
                'registration_status' => $registration->registration_status,
            ],
        ]);
    }

    private function getOrCreateActiveCart(string $userId): Cart
    {
        $cart = Cart::query()
            ->where('user_id', $userId)
            ->where('cart_status', 'active')
            ->orderByDesc('created_at')
            ->first();

        if ($cart) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $userId,
            'cart_status' => 'active',
            'currency_code' => 'MYR',
            'expires_at' => null,
        ]);
    }

    private function getUserMembershipType(string $userId): ?string
    {
        $type = UserMemberships::query()
            ->where('user_id', $userId)
            ->join('memberships', 'memberships.membership_id', '=', 'user_memberships.membership_id')
            ->where('user_memberships.membership_status', 'active')
            ->value('memberships.membership_type');

        return $type ? (string) $type : null;
    }

    private function getMembershipTypeIdByName(?string $membershipType): ?string
    {
        if (!$membershipType) {
            return null;
        }

        $id = MembershipTypes::query()
            ->whereRaw('LOWER(membership_type) = ?', [strtolower($membershipType)])
            ->value('membership_type_id');

        return $id ? (string) $id : null;
    }

    private function formatRegistrationSummary(EventRegistration $registration): array
    {
        return [
            'event_registration_id' => (string) $registration->event_registration_id,
            'registration_status' => (string) $registration->registration_status,
            'quantity' => (int) ($registration->quantity ?? 1),
            'confirmed_at' => $registration->confirmed_at ? (string) $registration->confirmed_at : null,
            'checked_in_at' => $registration->checked_in_at ? (string) $registration->checked_in_at : null,
            'checked_in_by_user_id' => $registration->checked_in_by_user_id ? (string) $registration->checked_in_by_user_id : null,
            'check_in_source' => $registration->check_in_source ? (string) $registration->check_in_source : null,
        ];
    }

    private function resolveEventPricing(Events $event, ?string $membershipTypeId, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $basePrice = (float) ($event->registration_type === 'paid' ? $event->base_price : 0);
        if ($basePrice < 0) {
            $basePrice = 0;
        }

        $rule = null;
        if ($event->registration_type === 'paid' && $membershipTypeId) {
            $rule = EventPricingRule::query()
                ->where('event_id', $event->event_id)
                ->where('membership_type_id', $membershipTypeId)
                ->where('is_active', true)
                ->where(function ($q) {
                    $now = now();
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) {
                    $now = now();
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->orderByDesc('created_at')
                ->first();
        }

        $discountPerUnit = 0.0;
        $finalPerUnit = $basePrice;

        if ($rule) {
            $value = (float) $rule->pricing_value;
            if ((string) $rule->pricing_rule_type === 'discount_percent') {
                $discountPerUnit = round($basePrice * ($value / 100), 2);
                $finalPerUnit = $basePrice - $discountPerUnit;
            } elseif ((string) $rule->pricing_rule_type === 'discount_fixed') {
                $discountPerUnit = min($basePrice, round($value, 2));
                $finalPerUnit = $basePrice - $discountPerUnit;
            } elseif ((string) $rule->pricing_rule_type === 'final_price') {
                $finalPerUnit = round($value, 2);
                $discountPerUnit = max(0, round($basePrice - $finalPerUnit, 2));
            }
        }

        if ($finalPerUnit < 0) {
            $finalPerUnit = 0;
        }

        $priceBeforeDiscount = round($basePrice * $quantity, 2);
        $discountAmount = round($discountPerUnit * $quantity, 2);
        $totalPrice = round($finalPerUnit * $quantity, 2);

        return [
            'quantity' => $quantity,
            'base_unit_price' => round($basePrice, 2),
            'price_before_discount' => $priceBeforeDiscount,
            'discount_amount' => $discountAmount,
            'unit_price' => round($finalPerUnit, 2),
            'total_price' => $totalPrice,
        ];
    }

    private function assertEventRsvpWindow(Events $event): void
    {
        $now = now();
        if ($event->rsvp_open_at && $now->lessThan($event->rsvp_open_at)) {
            abort(422, 'RSVP is not open yet.');
        }
        if ($event->rsvp_close_at && $now->greaterThan($event->rsvp_close_at)) {
            abort(422, 'RSVP is closed.');
        }
    }

    private function assertSeatAvailable(Events $event, int $requestedQuantity = 1, ?string $excludedRegistrationId = null): void
    {
        $seatLimit = $event->seat_limit ? (int) $event->seat_limit : 0;
        if ($seatLimit <= 0) {
            abort(422, 'Seat limit is not configured for this event.');
        }

        $now = now();
        $heldCount = (int) EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->whereIn('registration_status', ['draft', 'pending_payment'])
            ->whereNotNull('seat_hold_expires_at')
            ->where('seat_hold_expires_at', '>', $now)
            ->when($excludedRegistrationId, fn($query) => $query->where('event_registration_id', '!=', $excludedRegistrationId))
            ->sum('quantity');

        $confirmedCount = (int) EventRegistration::query()
            ->where('event_id', $event->event_id)
            ->where('registration_status', 'confirmed')
            ->when($excludedRegistrationId, fn($query) => $query->where('event_registration_id', '!=', $excludedRegistrationId))
            ->sum('quantity');

        if (($heldCount + $confirmedCount + max(1, $requestedQuantity)) > $seatLimit) {
            abort(422, 'No seats available for this event.');
        }
    }
}
