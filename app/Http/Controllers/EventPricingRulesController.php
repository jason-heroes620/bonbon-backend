<?php

namespace App\Http\Controllers;

use App\Models\EventPricingRule;
use App\Models\Events;
use App\Models\MembershipTypes;
use Illuminate\Http\Request;

class EventPricingRulesController extends Controller
{
    public function index(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $rules = EventPricingRule::query()
            ->where('event_id', $event->event_id)
            ->orderByDesc('is_active')
            ->orderBy('membership_type_id')
            ->orderBy('pricing_rule_type')
            ->get();

        $membershipTypes = MembershipTypes::query()
            ->where('is_active', true)
            ->orderBy('membership_type', 'asc')
            ->get(['membership_type_id', 'membership_type']);

        return response()->json([
            'data' => $rules,
            'membership_types' => $membershipTypes,
        ]);
    }

    public function store(Request $request, Events $event)
    {
        $this->assertAdmin($request);

        $validated = $request->validate([
            'membership_type_id' => ['nullable', 'uuid', 'exists:membership_types,membership_type_id'],
            'pricing_rule_type' => ['required', 'in:discount_percent,discount_fixed,final_price'],
            'pricing_value' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->validateRuleValue((string) $validated['pricing_rule_type'], (float) $validated['pricing_value']);

        if ((bool) $validated['is_active']) {
            $exists = EventPricingRule::query()
                ->where('event_id', $event->event_id)
                ->where('membership_type_id', $validated['membership_type_id'])
                ->where('is_active', true)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Only one active pricing rule is allowed for each membership type.',
                ], 422);
            }
        }

        $rule = EventPricingRule::create([
            'event_id' => $event->event_id,
            'membership_type_id' => $validated['membership_type_id'],
            'pricing_rule_type' => $validated['pricing_rule_type'],
            'pricing_value' => $validated['pricing_value'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'data' => $rule,
        ]);
    }

    public function update(Request $request, Events $event, EventPricingRule $rule)
    {
        $this->assertAdmin($request);

        if ((string) $rule->event_id !== (string) $event->event_id) {
            abort(404);
        }

        $validated = $request->validate([
            'membership_type_id' => ['nullable', 'uuid', 'exists:membership_types,membership_type_id'],
            'pricing_rule_type' => ['required', 'in:discount_percent,discount_fixed,final_price'],
            'pricing_value' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
        ]);

        $this->validateRuleValue((string) $validated['pricing_rule_type'], (float) $validated['pricing_value']);

        if ((bool) $validated['is_active']) {
            $exists = EventPricingRule::query()
                ->where('event_id', $event->event_id)
                ->where('membership_type_id', $validated['membership_type_id'])
                ->where('is_active', true)
                ->where('event_pricing_rule_id', '!=', $rule->event_pricing_rule_id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'Only one active pricing rule is allowed for each membership type.',
                ], 422);
            }
        }

        $rule->update([
            'membership_type_id' => $validated['membership_type_id'],
            'pricing_rule_type' => $validated['pricing_rule_type'],
            'pricing_value' => $validated['pricing_value'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'data' => $rule->fresh(),
        ]);
    }

    public function destroy(Request $request, Events $event, EventPricingRule $rule)
    {
        $this->assertAdmin($request);

        if ((string) $rule->event_id !== (string) $event->event_id) {
            abort(404);
        }

        $rule->update([
            'is_active' => false,
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }

    private function assertAdmin(Request $request): void
    {
        if ((string) $request->user()->role !== 'admin') {
            abort(403);
        }
    }

    private function validateRuleValue(string $type, float $value): void
    {
        if ($type === 'discount_percent' && ($value < 0 || $value > 100)) {
            abort(422, 'Discount percent must be between 0 and 100.');
        }
        if ($type === 'final_price' && $value <= 0) {
            abort(422, 'Final price must be greater than 0.');
        }
    }
}

