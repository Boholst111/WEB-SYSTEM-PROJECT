<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user has the required role
        if (!$this->hasRole($user, $role)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if user has the specified role.
     */
    private function hasRole($user, string $role): bool
    {
        // Check the role field in the users table
        if (property_exists($user, 'role') && $user->role === 'admin') {
            return true; // Admins have access to everything
        }
        
        switch ($role) {
            case 'admin':
                // Check if user has admin role
                return $user->role === 'admin';
                
            case 'user':
                // All authenticated users have user role
                return true;
                
            default:
                return false;
        }
    }
}