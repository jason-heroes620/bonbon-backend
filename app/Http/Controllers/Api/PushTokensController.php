<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPushTokens;
use Illuminate\Http\Request;

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

        $this->upsertUserToken(
            $user->user_id,
            $validated['expo_push_token'],
            $validated['device_name'] ?? null,
            $validated['platform'] ?? null,
        );

        return response()->json([
            'message' => 'Push token registered.',
        ]);
    }

    public function deviceToken(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'in:ios,android'],
        ]);

        $this->upsertUserToken(
            $user->user_id,
            $validated['token'],
            $validated['device_name'] ?? null,
            $validated['platform'] ?? null,
        );

        return response()->json([
            'message' => 'Device token registered.',
        ]);
    }

    private function upsertUserToken(string $userId, string $token, ?string $deviceName, ?string $platform): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        $deviceName = $deviceName !== null ? trim($deviceName) : null;
        if ($deviceName === '') {
            $deviceName = null;
        }

        $platform = $platform !== null ? strtolower(trim($platform)) : null;
        if ($platform === '') {
            $platform = null;
        }

        $recordByDevice = null;
        if ($platform !== null && $deviceName !== null) {
            $recordByDevice = UserPushTokens::query()
                ->where('user_id', $userId)
                ->where('platform', $platform)
                ->where('device_name', $deviceName)
                ->first();
        }

        if ($recordByDevice) {
            if ($recordByDevice->expo_push_token !== $token) {
                $recordByToken = UserPushTokens::query()
                    ->where('expo_push_token', $token)
                    ->first();

                if ($recordByToken) {
                    $recordByToken->update([
                        'user_id' => $userId,
                        'device_name' => $deviceName,
                        'platform' => $platform,
                        'last_seen_at' => now(),
                    ]);

                    if ((string) $recordByToken->getKey() !== (string) $recordByDevice->getKey()) {
                        UserPushTokens::destroy($recordByDevice->getKey());
                    }

                    return;
                }
            }

            $recordByDevice->update([
                'expo_push_token' => $token,
                'last_seen_at' => now(),
            ]);

            return;
        }

        $recordByToken = UserPushTokens::query()
            ->where('expo_push_token', $token)
            ->first();

        if ($recordByToken) {
            $recordByToken->update([
                'user_id' => $userId,
                'device_name' => $deviceName ?? $recordByToken->device_name,
                'platform' => $platform ?? $recordByToken->platform,
                'last_seen_at' => now(),
            ]);
            return;
        }

        UserPushTokens::query()->create([
            'user_id' => $userId,
            'expo_push_token' => $token,
            'device_name' => $deviceName,
            'platform' => $platform,
            'last_seen_at' => now(),
        ]);
    }
}
