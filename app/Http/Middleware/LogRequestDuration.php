<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestDuration
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // 1. Calculate duration using the framework's native start constant
        $duration = microtime(true) - LARAVEL_START;

        // 2. Format the duration to 4 decimal places
        $formattedDuration = number_format($duration, 4);

        // 3. Log the data to storage/logs/laravel.log
        Log::info('Request Processed', [
            'method'   => $request->getMethod(),
            'url'      => $request->fullUrl(),
            'duration' => "{$formattedDuration}s",
            'status'   => $response->getStatusCode(),
        ]);
    }
}
