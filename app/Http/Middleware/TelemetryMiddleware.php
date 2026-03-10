<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Exceptions\Handler;
use App\Services\MetricsCollector;

class TelemetryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');
        if (!$requestId) {
            $requestId = Str::uuid()->toString();
            $request->headers->set('X-Request-Id', $requestId);
        }

        $request->attributes->set('start_time', microtime(true));

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $startTime = $request->attributes->get('start_time');
        $latencyMs = $startTime ? (microtime(true) - $startTime) * 1000 : 0;

        $exception = $response->exception ?? null;
        
        $errorCategory = Handler::getErrorCategory($exception, $latencyMs);
        
        MetricsCollector::record($request, $response, $errorCategory, $latencyMs);
        
        $payloadSizeBytes = strlen($request->getContent());
        $responseSizeBytes = strlen($response->getContent() ?: '');

        $severity = ($errorCategory !== 'NONE' || $response->getStatusCode() >= 400) ? 'error' : 'info';

        $logData = [
            'request_id' => $request->header('X-Request-Id'),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent() ?: null,
            'query' => $request->getQueryString() ?: null,
            'payload_size_bytes' => $payloadSizeBytes,
            'response_size_bytes' => $responseSizeBytes,
            'route_name' => $request->route() ? $request->route()->getName() : 'unknown',
            'severity' => $severity,
            'build_version' => env('BUILD_VERSION', '1.0.0'),
            'host' => gethostname(),
            'latency_ms' => round($latencyMs, 2),
            'status_code' => $response->getStatusCode(),
            'error_category' => $errorCategory,
            'method' => $request->method(),
            'path' => $request->path(),
        ];

        if ($severity === 'error') {
            Log::error('API Request Failed', $logData);
        } else {
            Log::info('API Request Success', $logData);
        }
    }
}
