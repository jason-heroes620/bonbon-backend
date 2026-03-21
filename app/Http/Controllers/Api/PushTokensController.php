<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPushTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushTokensController extends Controller
{
    public function register(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'expo_push_token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
        ]);

        $record = UserPushTokens::query()
            ->where('expo_push_token', $validated['expo_push_token'])
            ->first();

        if ($record) {
            $record->update([
                'user_id' => $user->user_id,
                'device_name' => $validated['device_name'] ?? $record->device_name,
                'platform' => $validated['platform'] ?? $record->platform,
                'last_seen_at' => now(),
            ]);
        } else {
            UserPushTokens::create([
                'user_push_token_id' => (string) Str::uuid(),
                'user_id' => $user->user_id,
                'expo_push_token' => $validated['expo_push_token'],
                'device_name' => $validated['device_name'] ?? null,
                'platform' => $validated['platform'] ?? null,
                'last_seen_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Push token registered.',
        ]);
    }
}

