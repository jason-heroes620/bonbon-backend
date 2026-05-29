<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;

class FcmService
{
    protected string $projectId;
    protected string $credentialsPath;

    public function __construct()
    {
        $this->projectId = env('FCM_PROJECT_ID');
        $this->credentialsPath = base_path(env('FIREBASE_CREDENTIALS'));
    }

    /**
     * Fetch short-lived Google Access Token via Service Account
     */
    private function getAccessToken(): string
    {
        $client = new GoogleClient();
        $client->setAuthConfig($this->credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }

    /**
     * Send Push Notification to a Specific Device Token
     */
    public function sendPush(string $deviceToken, string $title, string $body, array $data = [])
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                // Custom data payload properties must contain string values only
                'data' => array_map('strval', $data),
                'android' => [
                    'notification' => [
                        'channel_id' => 'default', // Matches Expo Channel ID
                    ],
                ],
            ],
        ];

        $response = Http::withToken($this->getAccessToken())
            ->post($url, $payload);

        return $response->json();
    }
}
