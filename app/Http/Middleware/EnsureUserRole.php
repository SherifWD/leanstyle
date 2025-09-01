<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user('api');
        if (!$user || $user->role !== $role || $user->is_blocked) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}
