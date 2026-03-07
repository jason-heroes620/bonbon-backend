<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;

class SocialAuthController extends Controller
{
    public function google(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $client = new GoogleClient([
            'client_id' => env('GOOGLE_CLIENT_ID'),
        ]);

        $payload = $client->verifyIdToken($request->id_token);

        if (!$payload) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        $user = User::updateOrCreate(
            ['email' => $payload['email']],
            [
                'name' => $payload['name'] ?? '',
                'google_id' => $payload['sub'],
                'password' => bcrypt(str()->random(16)),
            ]
        );

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function apple(Request $request)
{
    $request->validate([
        'id_token' => 'required|string',
    ]);

    $appleKeys = Http::get('https://appleid.apple.com/auth/keys')->json();

    $keys = JWK::parseKeySet($appleKeys);

    try {
        $decoded = JWT::decode($request->id_token, $keys);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Invalid Apple token'], 401);
    }

    $appleUser = (array) $decoded;

    $user = User::updateOrCreate(
        ['email' => $appleUser['email'] ?? null],
        [
            'name' => $appleUser['email'] ?? 'Apple User',
            'apple_id' => $appleUser['sub'],
            'password' => bcrypt(str()->random(16)),
        ]
    );

    $token = $user->createToken('mobile')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => $user
    ]);
}
}
