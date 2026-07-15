<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventCheckIn;
use App\Models\Events;
use App\Models\EventRegistration;
use App\Models\TransactionTypes;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EventCheckInController extends Controller
{
    protected CreditService $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', 'BBLDAdmin')
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $deviceName = $validated['device_name'] ?? 'api';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->makeHidden(['password', 'remember_token']),
        ]);
    }

    public function showQr(Request $request, string $event_id)
    {
        $event = Events::query()
            ->whereKey($event_id)
            ->first();

        if (!$event) {
            return response()->json([
                'message' => 'Event not found',
            ], 404);
        }

        if (!$event->require_registration) {
            return response()->json([
                'message' => 'Registration is not enabled for this event.',
            ], 422);
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

        if ((string) $registration->registration_status !== 'confirmed') {
            return response()->json([
                'message' => 'Registration is not confirmed yet.',
            ], 422);
        }

        if ($registration->checked_in_at) {
            return response()->json([
                'message' => 'This registration has already been checked in.',
                'data' => [
                    'event_registration' => $this->formatRegistrationData($registration, $event, $request->user()),
                    'qr_value' => null,
                ],
            ], 409);
        }

        $payload = $this->buildAttendancePayload($registration, $event);

        return response()->json([
            'data' => [
                'event_registration' => $this->formatRegistrationData($registration, $event, $request->user()),
                'qr_value' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            ],
        ]);
    }

    public function listEvents(Request $request)
    {
        $scanner = $request->user();
        if (!$this->canScanAttendance($scanner)) {
            return response()->json([
                'message' => 'Scanner access is required.',
            ], 403);
        }

        $today = now()->toDateString();
        $events = Events::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->whereDate('event_end_date', '>=', $today)
            ->orderBy('event_start_date')
            ->orderBy('event_start_time')
            ->get([
                'event_id',
                'event_name',
                'event_start_date',
                'event_end_date',
                'event_start_time',
                'event_end_time',
                'event_location',
                'location_name',
            ]);

        $defaultEvent = $events->first(function ($event) use ($today) {
            $startDate = (string) ($event->event_start_date ?? '');
            $endDate = (string) ($event->event_end_date ?? $startDate);

            return $startDate !== ''
                && $endDate !== ''
                && $startDate <= $today
                && $endDate >= $today;
        });

        return response()->json([
            'data' => [
                'events' => $events->map(function ($event) {
                    return [
                        'event_id' => (string) $event->event_id,
                        'event_name' => (string) $event->event_name,
                        'event_start_date' => $event->event_start_date ? (string) $event->event_start_date : null,
                        'event_end_date' => $event->event_end_date ? (string) $event->event_end_date : null,
                        'event_start_time' => $event->event_start_time ? (string) $event->event_start_time : null,
                        'event_end_time' => $event->event_end_time ? (string) $event->event_end_time : null,
                        'event_location' => $event->location_name
                            ? (string) $event->location_name
                            : ($event->event_location ? (string) $event->event_location : null),
                    ];
                })->values(),
                'default_event_id' => $defaultEvent ? (string) $defaultEvent->event_id : null,
            ],
        ]);
    }

    public function validateQr(Request $request)
    {
        $validated = $request->validate([
            'payload' => ['required'],
            'event_id' => ['nullable', 'string'],
        ]);

        $scanner = $request->user();
        if (!$this->canScanAttendance($scanner)) {
            return response()->json([
                'message' => 'Scanner access is required.',
            ], 403);
        }

        $payload = $this->normalizePayloadInput($validated['payload']);
        $registration = $payload ? $this->findRegistrationByPayload($payload) : null;

        if ($registration && $payload) {
            $providedSignature = trim((string) ($payload['signature'] ?? ''));
            if ($providedSignature === '' || !hash_equals($this->signAttendancePayload($payload), $providedSignature)) {
                return response()->json([
                    'message' => 'Event attendance QR signature is invalid.',
                ], 422);
            }
        } else {
            $legacyPayload = $this->decodeLegacyUserPayload($validated['payload']);
            if (!$legacyPayload) {
                return response()->json([
                    'message' => 'Invalid event attendance QR payload.',
                ], 422);
            }

            $selectedEventId = trim((string) ($validated['event_id'] ?? ''));
            if ($selectedEventId === '') {
                return response()->json([
                    'message' => 'Please select an event before scanning a user QR code.',
                ], 422);
            }

            $registration = $this->findRegistrationByEventAndUser(
                $selectedEventId,
                trim((string) ($legacyPayload['user_id'] ?? ''))
            );

            if (!$registration) {
                return response()->json([
                    'message' => 'Confirmed registration not found for the selected event.',
                ], 404);
            }
        }

        $event = $registration->event;
        $user = $registration->user;
        if (!$event || !$user) {
            return response()->json([
                'message' => 'Event registration context is incomplete.',
            ], 422);
        }

        if ((string) $registration->registration_status !== 'confirmed') {
            return response()->json([
                'message' => 'This registration is not confirmed for attendance.',
                'data' => $this->formatRegistrationData($registration, $event, $user),
            ], 422);
        }

        if ($registration->checked_in_at) {
            return response()->json([
                'message' => 'This registration has already been checked in.',
                'data' => $this->formatRegistrationData($registration, $event, $user),
            ], 409);
        }

        return response()->json([
            'data' => $this->formatRegistrationData($registration, $event, $user),
        ]);
    }

    public function confirm(Request $request, string $event_registration_id)
    {
        $validated = $request->validate([
            'check_in_source' => ['nullable', 'string', 'in:merchant_app,admin_web'],
        ]);

        $scanner = $request->user();
        if (!$this->canScanAttendance($scanner)) {
            return response()->json([
                'message' => 'Scanner access is required.',
            ], 403);
        }

        $result = DB::transaction(function () use ($event_registration_id, $validated, $scanner) {
            $registration = EventRegistration::query()
                ->whereKey($event_registration_id)
                ->lockForUpdate()
                ->first();

            if (!$registration) {
                return [
                    'status' => 404,
                    'body' => ['message' => 'Event registration not found.'],
                ];
            }

            $event = Events::query()
                ->whereKey((string) $registration->event_id)
                ->first();

            $user = User::query()
                ->whereKey((string) $registration->user_id)
                ->first();

            if (!$event) {
                return [
                    'status' => 404,
                    'body' => ['message' => 'Event not found'],
                ];
            }

            if (!$user) {
                return [
                    'status' => 404,
                    'body' => ['message' => 'User not found'],
                ];
            }

            if ((string) $registration->registration_status !== 'confirmed') {
                return [
                    'status' => 422,
                    'body' => [
                        'message' => 'This registration is not confirmed for attendance.',
                        'data' => $this->formatRegistrationData($registration, $event, $user),
                    ],
                ];
            }

            if ($registration->checked_in_at) {
                return [
                    'status' => 409,
                    'body' => [
                        'message' => 'This registration has already been checked in.',
                        'data' => $this->formatRegistrationData($registration, $event, $user),
                    ],
                ];
            }

            $checkInSource = (string) ($validated['check_in_source'] ?? $this->defaultCheckInSource($scanner));
            $transactionType = 'check_in';
            $credit = TransactionTypes::query()
                ->where('transaction_type', $transactionType)
                ->whereDate('effective_date', '<=', today())
                ->where(function ($query) {
                    $query
                        ->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>=', today());
                })
                ->where('is_active', true)
                ->orderByDesc('effective_date')
                ->value('credit_amount');

            if ($credit === null) {
                return [
                    'status' => 422,
                    'body' => ['message' => 'No active credit configuration found for check-in'],
                ];
            }

            $registration->update([
                'checked_in_at' => now(),
                'checked_in_by_user_id' => (string) $scanner->user_id,
                'check_in_source' => $checkInSource,
            ]);

            EventCheckIn::query()->create([
                'user_id' => (string) $registration->user_id,
                'event_id' => (string) $registration->event_id,
                'event_registration_id' => (string) $registration->event_registration_id,
                'checked_in_by_user_id' => (string) $scanner->user_id,
                'check_in_source' => $checkInSource,
            ]);

            $this->creditService->addCredits(
                $user,
                $credit,
                $transactionType,
                null,
                'Checked in for event ' . $event->event_name
            );

            $freshRegistration = $registration->fresh();

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Checked in successfully',
                    'data' => $this->formatRegistrationData($freshRegistration, $event, $user),
                ],
            ];
        });

        return response()->json($result['body'], $result['status']);
    }

    private function canScanAttendance(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return (string) $user->role === 'BBLDAdmin';
    }

    private function defaultCheckInSource(User $user): string
    {
        return (string) $user->role === 'BBLDAdmin' ? 'admin_web' : 'merchant_app';
    }

    private function normalizePayloadInput(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function buildAttendancePayload(EventRegistration $registration, Events $event): array
    {
        $payload = [
            'event_registration_id' => (string) $registration->event_registration_id,
            'event_id' => (string) $event->event_id,
            'user_id' => (string) $registration->user_id,
            'quantity' => (int) ($registration->quantity ?? 1),
            'ts' => now()->timestamp,
        ];

        $payload['signature'] = $this->signAttendancePayload($payload);

        return $payload;
    }

    private function signAttendancePayload(array $payload): string
    {
        $canonical = $payload;
        unset($canonical['signature']);
        ksort($canonical);

        return hash_hmac(
            'sha256',
            json_encode($canonical, JSON_UNESCAPED_SLASHES),
            $this->attendanceQrSecret()
        );
    }

    private function attendanceQrSecret(): string
    {
        return (string) (env('QR_SECRET') ?: config('app.key'));
    }

    private function decodeLegacyUserPayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $this->isLegacyUserPayload($payload) ? $payload : null;
        }

        if (!is_string($payload)) {
            return null;
        }

        $trimmed = trim($payload);
        if ($trimmed === '') {
            return null;
        }

        $decodedJson = json_decode($trimmed, true);
        if (is_array($decodedJson) && $this->isLegacyUserPayload($decodedJson)) {
            return $decodedJson;
        }

        $base64Decoded = base64_decode($trimmed, true);
        if ($base64Decoded !== false) {
            $decodedBase64Json = json_decode($base64Decoded, true);
            if (is_array($decodedBase64Json) && $this->isLegacyUserPayload($decodedBase64Json)) {
                return $decodedBase64Json;
            }
        }

        $secret = $this->attendanceQrSecret();
        $decrypted = openssl_decrypt(
            $trimmed,
            'AES-256-CBC',
            hash('sha256', $secret, true),
            0,
            md5($secret, true)
        );

        if (!is_string($decrypted) || trim($decrypted) === '') {
            return null;
        }

        $decodedEncryptedJson = json_decode($decrypted, true);
        return is_array($decodedEncryptedJson) && $this->isLegacyUserPayload($decodedEncryptedJson)
            ? $decodedEncryptedJson
            : null;
    }

    private function isLegacyUserPayload(array $payload): bool
    {
        return trim((string) ($payload['user_id'] ?? '')) !== ''
            && trim((string) ($payload['email'] ?? '')) !== ''
            && trim((string) ($payload['ts'] ?? '')) !== '';
    }

    private function findRegistrationByPayload(array $payload): ?EventRegistration
    {
        $registrationId = trim((string) ($payload['event_registration_id'] ?? ''));
        $eventId = trim((string) ($payload['event_id'] ?? ''));
        $userId = trim((string) ($payload['user_id'] ?? ''));

        if ($registrationId === '' || $eventId === '' || $userId === '') {
            return null;
        }

        return EventRegistration::query()
            ->with(['event', 'user'])
            ->whereKey($registrationId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();
    }

    private function findRegistrationByEventAndUser(string $eventId, string $userId): ?EventRegistration
    {
        if ($eventId === '' || $userId === '') {
            return null;
        }

        return EventRegistration::query()
            ->with(['event', 'user'])
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();
    }

    private function formatRegistrationData(EventRegistration $registration, Events $event, User $user): array
    {
        return [
            'event_registration_id' => (string) $registration->event_registration_id,
            'event_id' => (string) $event->event_id,
            'event_name' => (string) $event->event_name,
            'attendee_name' => trim((string) $user->first_name . ' ' . (string) $user->last_name),
            'quantity' => (int) ($registration->quantity ?? 1),
            'registration_status' => (string) $registration->registration_status,
            'confirmed_at' => $registration->confirmed_at ? (string) $registration->confirmed_at : null,
            'checked_in_at' => $registration->checked_in_at ? (string) $registration->checked_in_at : null,
            'check_in_source' => $registration->check_in_source ? (string) $registration->check_in_source : null,
            'event_start_date' => $event->event_start_date ? (string) $event->event_start_date : null,
            'event_start_time' => $event->event_start_time ? (string) $event->event_start_time : null,
            'event_end_time' => $event->event_end_time ? (string) $event->event_end_time : null,
            'event_location' => $event->location_name
                ? (string) $event->location_name
                : ($event->event_location ? (string) $event->event_location : null),
        ];
    }
}
