<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    /**
     * Store Core Web Vitals metrics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeWebVitals(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|in:CLS,FID,FCP,LCP,TTFB,INP',
            'value' => 'required|numeric',
            'rating' => 'required|string|in:good,needs-improvement,poor',
            'delta' => 'required|numeric',
            'id' => 'required|string',
            'navigationType' => 'nullable|string',
        ]);

        // Log the metric
        Log::channel('performance')->info('Web Vitals Metric', [
            'metric' => $validated['name'],
            'value' => $validated['value'],
            'rating' => $validated['rating'],
            'user_agent' => $request->userAgent(),
            'url' => $request->header('referer'),
            'timestamp' => now(),
        ]);

        // Store in cache for real-time monitoring
        $cacheKey = "web_vitals:{$validated['name']}:" . date('Y-m-d-H');
        $metrics = Cache::get($cacheKey, []);
        $metrics[] = [
            'value' => $validated['value'],
            'rating' => $validated['rating'],
            'timestamp' => now()->timestamp,
        ];
        Cache::put($cacheKey, $metrics, now()->addHours(24));

        // TODO: Store in database for long-term analysis
        // WebVitalsMetric::create($validated);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Store custom performance metrics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePerformance(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:custom-metric,component-render,api-call,navigation,page-load-summary',
            'name' => 'nullable|string',
            'component' => 'nullable|string',
            'endpoint' => 'nullable|string',
            'from' => 'nullable|string',
            'to' => 'nullable|string',
            'duration' => 'nullable|numeric',
            'status' => 'nullable|integer',
            'metrics' => 'nullable|array',
            'timestamp' => 'required|integer',
        ]);

        // Log the metric
        Log::channel('performance')->info('Performance Metric', [
            'type' => $validated['type'],
            'data' => $validated,
            'user_agent' => $request->userAgent(),
            'url' => $request->header('referer'),
        ]);

        // Store in cache for real-time monitoring
        $cacheKey = "performance:{$validated['type']}:" . date('Y-m-d-H');
        $metrics = Cache::get($cacheKey, []);
        $metrics[] = $validated;
        Cache::put($cacheKey, $metrics, now()->addHours(24));

        // TODO: Store in database for long-term analysis
        // PerformanceMetric::create($validated);

        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Get Web Vitals summary
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWebVitalsSummary(Request $request)
    {
        $hours = $request->input('hours', 24);
        $summary = [];

        $metrics = ['CLS', 'FID', 'FCP', 'LCP', 'TTFB', 'INP'];
        
        foreach ($metrics as $metric) {
            $data = [];
            
            for ($i = 0; $i < $hours; $i++) {
                $hour = now()->subHours($i)->format('Y-m-d-H');
                $cacheKey = "web_vitals:{$metric}:{$hour}";
                $hourData = Cache::get($cacheKey, []);
                $data = array_merge($data, $hourData);
            }

            if (!empty($data)) {
                $values = array_column($data, 'value');
                $summary[$metric] = [
                    'count' => count($values),
                    'avg' => round(array_sum($values) / count($values), 2),
                    'min' => min($values),
                    'max' => max($values),
                    'p50' => $this->percentile($values, 50),
                    'p75' => $this->percentile($values, 75),
                    'p95' => $this->percentile($values, 95),
                    'p99' => $this->percentile($values, 99),
                    'ratings' => [
                        'good' => count(array_filter($data, fn($d) => $d['rating'] === 'good')),
                        'needs-improvement' => count(array_filter($data, fn($d) => $d['rating'] === 'needs-improvement')),
                        'poor' => count(array_filter($data, fn($d) => $d['rating'] === 'poor')),
                    ],
                ];
            }
        }

        return response()->json($summary);
    }

    /**
     * Get performance metrics summary
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPerformanceSummary(Request $request)
    {
        $hours = $request->input('hours', 24);
        $type = $request->input('type', 'all');
        
        $types = $type === 'all' 
            ? ['custom-metric', 'component-render', 'api-call', 'navigation', 'page-load-summary']
            : [$type];

        $summary = [];

        foreach ($types as $metricType) {
            $data = [];
            
            for ($i = 0; $i < $hours; $i++) {
                $hour = now()->subHours($i)->format('Y-m-d-H');
                $cacheKey = "performance:{$metricType}:{$hour}";
                $hourData = Cache::get($cacheKey, []);
                $data = array_merge($data, $hourData);
            }

            if (!empty($data)) {
                $durations = array_filter(array_column($data, 'duration'));
                
                if (!empty($durations)) {
                    $summary[$metricType] = [
                        'count' => count($data),
                        'avg_duration' => round(array_sum($durations) / count($durations), 2),
                        'min_duration' => min($durations),
                        'max_duration' => max($durations),
                        'p50' => $this->percentile($durations, 50),
                        'p95' => $this->percentile($durations, 95),
                    ];
                }
            }
        }

        return response()->json($summary);
    }

    /**
     * Calculate percentile
     *
     * @param array $values
     * @param int $percentile
     * @return float
     */
    private function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($lower === $upper) {
            return $values[$lower];
        }

        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }
}
