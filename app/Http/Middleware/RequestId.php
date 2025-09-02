<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        $rid = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();
        app()->instance('request_id', $rid);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $rid);
        return $response;
    }
}