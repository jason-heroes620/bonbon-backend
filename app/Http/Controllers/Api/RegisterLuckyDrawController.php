<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LuckyDrawSession;
use App\Models\LuckyDrawEntries;
use App\Models\User;
use App\Models\UserMemberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RegisterLuckyDrawController extends Controller
{
    public function registerUser(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'user_id' => ['required', 'string'],
        ]);

        // check for user membership type, if user has free membership, eligible for 1 lucky draw,
        // if user has standard membership, eligible for 5 lucky draw
        $user = User::query()
            ->where('user_id', $validated['user_id'])
            ->where('is_active', true)
            ->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'user_id' => ['User not found or inactive.'],
            ]);
        }

        // check if user already has submitted ticket

        // check if user already has submitted ticket
        $luckyDrawSession = LuckyDrawSession::query()
            ->where('session_status', 'pending')
            ->first()->id;
        $ifExist = LuckyDrawEntries::query()
            ->where('session_id', $luckyDrawSession)
            ->where('user_id', $validated['user_id'])
            ->first();
        if ($ifExist) {
            throw ValidationException::withMessages([
                'user_id' => ['User already has submitted ticket.'],
            ]);
        }

        $membership = UserMemberships::query()
            ->with('membership')
            ->where('user_id', $validated['user_id'])
            ->where('membership_status', 'active')
            ->first();
        if (!$membership) {
            throw ValidationException::withMessages([
                'user_id' => ['User has no active membership.'],
            ]);
        }
        Log::info($membership);

        $eligible = $membership->membership->membership_type === 'Standard' ? 5 : 1;

        LuckyDrawEntries::query()->create([
            'session_id' => $luckyDrawSession,
            'user_id' => $validated['user_id'],
            'email' => $validated['email'],
            'weight' => $eligible,
            'is_winner' => false,
        ]);

        //return json response membership type and eligible lucky draw
        return response()->json([
            'membership_type' => $membership->membership->membership_type,
            'eligible' => $eligible,
        ]);
    }
}
