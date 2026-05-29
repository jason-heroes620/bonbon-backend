<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        try {
            $client = new GoogleClient();
            $client->setAuthConfig($this->credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();
            $accessToken = $token['access_token'] ?? null;
            if (!is_string($accessToken) || $accessToken === '') {
                Log::warning('FCM access token missing from Google client response.', [
                    'has_error' => array_key_exists('error', (array) $token),
                    'error' => $token['error'] ?? null,
                    'error_description' => $token['error_description'] ?? null,
                ]);
                return '';
            }

            return $accessToken;
        } catch (\Throwable $e) {
            Log::error('FCM access token generation failed.', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Send Push Notification to a Specific Device Token
     */
    public function sendPush(string $deviceToken, string $title, string $body, array $data = [])
    {
        $tokenFingerprint = substr(hash('sha256', $deviceToken), 0, 12);

        if (empty($this->projectId) || empty($this->credentialsPath)) {
            Log::warning('FCM configuration missing.', [
                'project_id_present' => !empty($this->projectId),
                'credentials_path_present' => !empty($this->credentialsPath),
                'token_fingerprint' => $tokenFingerprint,
            ]);
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $accessToken = $this->getAccessToken();
        if ($accessToken === '') {
            Log::error('FCM send aborted because access token is empty.', [
                'token_fingerprint' => $tokenFingerprint,
            ]);
            return [
                'ok' => false,
                'error' => 'access_token_empty',
            ];
        }

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

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($url, $payload);

            $json = $response->json();
            if ($response->successful()) {
                Log::info('FCM push sent successfully.', [
                    'token_fingerprint' => $tokenFingerprint,
                    'message_name' => is_array($json) ? ($json['name'] ?? null) : null,
                    'status' => $response->status(),
                ]);

                return $json;
            }

            Log::warning('FCM push send failed.', [
                'token_fingerprint' => $tokenFingerprint,
                'status' => $response->status(),
                'response' => $json,
            ]);

            return $json;
        } catch (\Throwable $e) {
            Log::error('FCM push send exception.', [
                'token_fingerprint' => $tokenFingerprint,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }
}
