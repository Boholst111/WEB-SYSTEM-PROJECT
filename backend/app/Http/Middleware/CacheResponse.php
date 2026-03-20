<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $ttl = 3600): Response
    {
        // Only cache GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Don't cache authenticated user-specific requests
        if ($request->user()) {
            return $next($request);
        }

        // Generate cache key from request
        $cacheKey = $this->getCacheKey($request);

        // Try to get cached response
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse !== null) {
            return response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers'])
                ->header('X-Cache', 'HIT');
        }

        // Process request
        $response = $next($request);

        // Cache successful responses
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ], $ttl);
        }

        return $response->header('X-Cache', 'MISS');
    }

    /**
     * Generate cache key from request
     */
    private function getCacheKey(Request $request): string
    {
        $uri = $request->getRequestUri();
        $query = $request->getQueryString();
        
        return 'response:' . md5($uri . $query);
    }
}
