<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get security headers from config
        $headers = config('security.headers', []);

        // Apply security headers
        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Add Content Security Policy if enabled
        if (config('security.csp.enabled', true)) {
            $cspDirectives = $this->buildCspDirectives();
            $response->headers->set('Content-Security-Policy', $cspDirectives);
        }

        return $response;
    }

    /**
     * Build Content Security Policy directives string
     */
    private function buildCspDirectives(): string
    {
        $directives = config('security.csp.directives', []);
        $cspParts = [];

        foreach ($directives as $directive => $sources) {
            if (is_array($sources)) {
                $cspParts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $cspParts);
    }
}
