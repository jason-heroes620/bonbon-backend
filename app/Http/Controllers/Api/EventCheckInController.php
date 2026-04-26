<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventCheckIn;
use App\Models\Events;
use App\Models\TransactionTypes;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EventCheckInController extends Controller
{
    protected $creditService;

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

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string'],
            'event_id' => ['required', 'string'],
        ]);

        $event = Events::query()
            ->where('event_id', $validated['event_id'])
            ->first();

        if (!$event) {
            return response()->json([
                'message' => 'Event not found',
            ], 404);
        }

        $result = DB::transaction(function () use ($validated, $event) {
            $user = User::query()
                ->where('user_id', $validated['user_id'])
                ->lockForUpdate()
                ->first();

            if (!$user) {
                return [
                    'status' => 404,
                    'body' => ['message' => 'User not found'],
                ];
            }

            $checkIn = EventCheckIn::query()
                ->where('user_id', $user->user_id)
                ->where('event_id', $event->event_id)
                ->whereDate('created_at', today())
                ->first();

            if ($checkIn) {
                return [
                    'status' => 400,
                    'body' => ['message' => 'User already checked in for this event'],
                ];
            }

            EventCheckIn::query()->create([
                'user_id' => $user->user_id,
                'event_id' => $event->event_id,
            ]);

            $transactionType = 'check_in';
            $credit = TransactionTypes::query()
                ->where('transaction_type', $transactionType)
                ->whereDate('effective_date', '<=', today())
                ->where(function ($query) {
                    $query
                        ->whereNull('expiration_date')
                        ->orWhereDate('expiration_date', '>=', today());
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

            $this->creditService->addCredits(
                $user,
                $credit,
                $transactionType,
                null,
                'Checked in for event ' . $event->event_name
            );

            return [
                'status' => 200,
                'body' => ['message' => 'Checked in successfully'],
            ];
        });

        return response()->json($result['body'], $result['status']);
    }
}
