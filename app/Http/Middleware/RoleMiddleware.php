<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage:
     *   ->middleware('role:shop_owner')
     *   ->middleware('role:delivery_boy')
     *   ->middleware('role:shop_owner,admin') // any of these roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Resolve the user from the most likely guards without requiring a guard param
        $user = $request->user()
            ?? auth()->user()
            ?? auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // If no roles passed, allow (or flip to deny based on your policy)
        if (empty($roles)) {
            return $next($request);
        }

        // Accept comma-separated values in a single arg as well
        $expanded = [];
        foreach ($roles as $r) {
            foreach (preg_split('/\s*,\s*/', $r, -1, PREG_SPLIT_NO_EMPTY) as $one) {
                $expanded[] = $one;
            }
        }

        if (!in_array((string)$user->role, $expanded, true)) {
            return response()->json(['message' => 'Forbidden (wrong role)'], 403);
        }

        return $next($request);
    }
}
