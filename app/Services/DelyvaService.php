<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DelyvaService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.delyva.api_key')) !== ''
            && trim((string) config('services.delyva.customer_id')) !== ''
            && trim((string) config('services.delyva.company_id')) !== '';
    }

    public function quote(array $origin, array $destination, float $weightKg): array
    {
        $response = $this->request('service/instantQuote', [
            'customerId' => $this->customerId(),
            'origin' => $origin,
            'destination' => $destination,
            'weight' => [
                'unit' => 'kg',
                'value' => round(max($weightKg, 0.1), 2),
            ],
            'itemType' => $this->itemType(),
        ]);

        // Log::info('quote');
        // Log::info($response);
        $services = data_get($response, 'data.services', []);
        if (!is_array($services)) {
            return [];
        }

        return collect($services)
            ->map(function ($service) {
                // Fix: Target the nested 'service' array key
                $companyCode = (string) data_get($service, 'service.code');
                $providerName = (string) data_get($service, 'service.name');

                $amount = (float) data_get($service, 'price.amount', 0);
                $currency = (string) data_get($service, 'price.currency', 'MYR');

                if ($companyCode === '' || $amount <= 0) {
                    return null;
                }

                return [
                    'service_code' => $companyCode,
                    'service_name' => $providerName,
                    'provider_name' => $providerName,
                    'amount' => round($amount, 2),
                    'currency' => $currency,
                    'raw' => $service,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function createOrder(array $payload): array
    {
        return $this->request('order', $payload);
    }

    public function processOrder(string $delyvaOrderId, array $payload): array
    {
        return $this->request('order/' . rawurlencode($delyvaOrderId) . '/process', $payload, 'POST');
    }

    public function getLabel(string $delyvaOrderId): string
    {
        return $this->requestRaw('order/' . rawurlencode($delyvaOrderId) . '/label', [], 'GET');
    }

    public function trackOrder(string $companyId, string $consignmentNo, string $resultType = 'latestFirst'): array
    {
        return $this->request('order/track', [
            'companyId' => $companyId,
            'consignmentNo' => $consignmentNo,
            'resultType' => $resultType,
        ], 'POST');
    }

    public function getOrderDetails(string $delyvaOrderId)
    {
        $baseUrl = rtrim((string) config('services.delyva.base_url'), '/');
        $apiKey = trim((string) config('services.delyva.api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('Delyva is not configured.');
        }

        $url = $baseUrl . '/' . ltrim('order/' . rawurlencode($delyvaOrderId), '/');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Delyvax-Access-Token' => $apiKey,
        ])->get($url);

        return $response->json() ?? [];
    }

    private function request(string $path, array $payload, string $method = 'POST'): array
    {
        $baseUrl = rtrim((string) config('services.delyva.base_url'), '/');
        $apiKey = trim((string) config('services.delyva.api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('Delyva is not configured.');
        }

        $url = $baseUrl . '/' . ltrim($path, '/');
        $method = strtoupper($method);

        if ($method === 'GET') {
            $query = http_build_query($payload);
            if ($query !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $query;
            }
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Delyvax-Access-Token' => $apiKey,
            ])->get($url);
        } else {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Delyvax-Access-Token' => $apiKey,
            ])->post($url, $payload);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'message')
                ?? data_get($response->json(), 'error')
                ?? 'Delyva request failed.';
            Log::error('Delyva request failed:', [
                'url' => $url,
                'method' => $method,
                'payload' => $payload,
                'response' => $response->json(),
            ]);

            throw new \RuntimeException((string) $message);
        }
        return $response->json() ?? [];
    }

    private function requestRaw(string $path, array $payload, string $method = 'GET'): string
    {
        $baseUrl = rtrim((string) config('services.delyva.base_url'), '/');
        $apiKey = trim((string) config('services.delyva.api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('Delyva is not configured.');
        }

        $url = $baseUrl . '/' . ltrim($path, '/');
        $method = strtoupper($method);

        if ($method === 'GET') {
            $query = http_build_query($payload);
            if ($query !== '') {
                $url .= (str_contains($url, '?') ? '&' : '?') . $query;
            }
            $response = Http::withHeaders([
                'X-Delyvax-Access-Token' => $apiKey,
            ])->get($url);
        } else {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Delyvax-Access-Token' => $apiKey,
            ])->post($url, $payload);
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'message')
                ?? data_get($response->json(), 'error')
                ?? 'Delyva request failed.';
            throw new \RuntimeException((string) $message);
        }
        return (string) $response->body();
    }

    private function customerId(): string|int
    {
        $customerId = trim((string) config('services.delyva.customer_id'));

        return is_numeric($customerId) ? (int) $customerId : $customerId;
    }

    private function itemType(): string
    {
        $itemType = strtoupper(trim((string) config('services.delyva.item_type')));

        return in_array($itemType, ['PARCEL', 'PACKAGE', 'BULKY'], true)
            ? $itemType
            : 'PARCEL';
    }
}
