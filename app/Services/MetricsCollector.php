<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetricsCollector
{
    private static $buckets = [0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    public static function record(Request $request, Response $response, string $errorCategory, float $latencyMs): void
    {
        $path = $request->path();
        if ($path === 'metrics' || $path === 'up') {
            return;
        }
        
        $method = $request->method();
        $status = (string)$response->getStatusCode();
        
        $latencySec = $latencyMs / 1000.0;

        self::incrementCounter('http_requests_total', [
            'method' => $method,
            'path' => $path,
            'status' => $status
        ]);

        if ($errorCategory !== 'NONE') {
            self::incrementCounter('http_errors_total', [
                'method' => $method,
                'path' => $path,
                'error_category' => $errorCategory
            ]);
        }
        
        self::recordHistogram('http_request_duration_seconds', [
            'method' => $method,
            'path' => $path,
        ], $latencySec);
    }

    private static function incrementCounter(string $name, array $labels, float $value = 1.0)
    {
        ksort($labels);
        $labelsJson = json_encode($labels);
        
        DB::table('metrics')->upsert(
            ['type' => 'counter', 'name' => $name, 'labels' => $labelsJson, 'value' => $value],
            ['type', 'name', 'labels'],
            ['value' => DB::raw('value + ' . $value)]
        );
    }

    private static function recordHistogram(string $name, array $labels, float $value)
    {
        ksort($labels);
        
        self::incrementCounter($name . '_sum', $labels, $value);
        self::incrementCounter($name . '_count', $labels, 1.0);
        
        foreach (self::$buckets as $bucket) {
            if ($value <= $bucket) {
                $bucketLabels = $labels;
                $bucketLabels['le'] = (string)$bucket;
                self::incrementCounter($name . '_bucket', $bucketLabels, 1.0);
            }
        }
        
        $bucketLabels = $labels;
        $bucketLabels['le'] = '+Inf';
        self::incrementCounter($name . '_bucket', $bucketLabels, 1.0);
    }
}
