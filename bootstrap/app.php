<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Helper to keep your JSON shape consistent
        $json = function (string $msg, int $status = 422, array $extra = []) {
            return response()->json(array_merge([
                'result' => false,
                'msg'    => $msg,
                'data'   => (object)[],
            ], $extra), $status);
        };

        // 422: Validation
        $exceptions->render(function (ValidationException $e) use ($json) {
            $errors = $e->errors();
            $first  = $e->getMessage() ?: collect($errors)->flatten()->first() ?: 'Validation error';
            return $json($first, 422, ['errors' => $errors]);
        });

        // 401: Unauthenticated
        $exceptions->render(function (AuthenticationException $e) use ($json) {
            return $json('Unauthenticated', 401);
        });

        // 403: Forbidden/Authorization
        $exceptions->render(function (AuthorizationException $e) use ($json) {
            return $json('Forbidden', 403);
        });

        // 404: Model not found (route model binding)
        $exceptions->render(function (ModelNotFoundException $e) use ($json) {
            return $json('Resource not found', 404);
        });

        // 404: Route not found
        $exceptions->render(function (NotFoundHttpException $e) use ($json) {
            return $json('Endpoint not found', 404);
        });

        // 405: Wrong HTTP method
        $exceptions->render(function (MethodNotAllowedHttpException $e) use ($json) {
            return $json('Method not allowed', 405);
        });

        // 429: Rate limit
        $exceptions->render(function (ThrottleRequestsException $e) use ($json) {
            return $json('Too many requests', 429, [
                'retry_after' => method_exists($e, 'getHeaders') ? ($e->getHeaders()['Retry-After'] ?? null) : null,
            ]);
        });

        // JWT: invalid/expired/missing → 401/403
        $exceptions->render(function (TokenInvalidException $e) use ($json) {
            return $json('Invalid token', 401);
        });
        $exceptions->render(function (TokenExpiredException $e) use ($json) {
            return $json('Token expired', 401);
        });
        $exceptions->render(function (JWTException $e) use ($json) {
            // Covers "token not provided", "could not parse token", etc.
            return $json('JWT error', 401);
        });

        // Any other HttpException: keep status but normalize body
        $exceptions->render(function (HttpExceptionInterface $e) use ($json) {
            return $json($e->getMessage() ?: 'HTTP error', $e->getStatusCode());
        });

        // Last-resort: unknown exceptions → 500 JSON (no stack trace)
        $exceptions->render(function (\Throwable $e) use ($json) {
            // Log it for debugging
            report($e);
            return $json('Server error', 500);
        });
    })
    ->create();
