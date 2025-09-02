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
            'request.id' => \App\Http\Middleware\RequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

    $json = function (string $msg, int $status = 422, $extra = [], ?\Throwable $e = null) {
        // Normalize $extra to an array
        if (is_object($extra)) {
            $extra = (array) $extra;
        } elseif (!is_array($extra)) {
            $extra = [];
        }

        $code = $extra['code'] ?? null;

        $payload = [
            'result'   => false,
            'msg'      => $msg,
            'code'     => $code,
            'data'     => (object) [],
            'trace_id' => app()->bound('request_id') ? app('request_id') : null,
        ];

        // Put developer-friendly extra under data.debug (only in debug)
        if (!empty($extra)) {
            $debug = $extra;
            unset($debug['code']); // don’t duplicate code under debug
            if (!empty($debug) && config('app.debug')) {
                $payload['data'] = ['debug' => $debug];
            }
        }

        // Include exception details only in debug
        if ($e && config('app.debug')) {
            $payload['data']['debug'] = array_merge($payload['data']['debug'] ?? [], [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'previous'  => $e->getPrevious()?->getMessage(),
            ]);
        }

        return response()->json($payload, $status);
    };

    // 422: Validation
    $exceptions->render(function (ValidationException $e) use ($json) {
        $errors = $e->errors();
        $first  = $e->getMessage() ?: collect($errors)->flatten()->first() ?: 'Validation error';
        return $json($first, 422, ['errors' => $errors, 'code' => 'VALIDATION_ERROR'], $e);
    });

    // 401: Unauthenticated
    $exceptions->render(function (AuthenticationException $e) use ($json) {
        return $json('Unauthenticated', 401, ['code' => 'UNAUTHENTICATED'], $e);
    });

    // 403: Forbidden
    $exceptions->render(function (AuthorizationException $e) use ($json) {
        return $json('Forbidden', 403, ['code' => 'FORBIDDEN'], $e);
    });

    // 404: Model not found
    $exceptions->render(function (ModelNotFoundException $e) use ($json) {
        $extra = ['code' => 'MODEL_NOT_FOUND'];
        if (config('app.debug')) {
            $extra['model'] = $e->getModel();
            $extra['ids']   = $e->getIds();
        }
        return $json('Resource not found', 404, $extra, $e);
    });

    // 404: Route not found
    $exceptions->render(function (NotFoundHttpException $e) use ($json) {
        return $json('Endpoint not found', 404, ['code' => 'ROUTE_NOT_FOUND'], $e);
    });

    // 405: Wrong HTTP method
    $exceptions->render(function (MethodNotAllowedHttpException $e) use ($json) {
        return $json('Method not allowed', 405, ['code' => 'METHOD_NOT_ALLOWED'], $e);
    });

    // 429: Rate limit
    $exceptions->render(function (ThrottleRequestsException $e) use ($json) {
        $retry = method_exists($e, 'getHeaders') ? ($e->getHeaders()['Retry-After'] ?? null) : null;
        return $json('Too many requests', 429, ['retry_after' => $retry, 'code' => 'TOO_MANY_REQUESTS'], $e);
    });

    // JWT issues → 401
    $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) use ($json) {
        return $json('Invalid token', 401, ['code' => 'TOKEN_INVALID'], $e);
    });
    $exceptions->render(function (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) use ($json) {
        return $json('Token expired', 401, ['code' => 'TOKEN_EXPIRED'], $e);
    });
    $exceptions->render(function (\Tymon\JWTAuth\Exceptions\JWTException $e) use ($json) {
        return $json('JWT error', 401, ['code' => 'JWT_ERROR'], $e);
    });

    // Database / SQL errors → 422 (or 409 if constraint)
    $exceptions->render(function (\Illuminate\Database\QueryException $e) use ($json) {
        $sqlState   = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;
        $driverMsg  = $e->errorInfo[2] ?? null;

        $status = 422;
        $code   = 'DB_QUERY_ERROR';

        if (in_array($driverCode, [1062, 1451, 1452], true)) {
            $status = 409;
            $code   = 'DB_CONSTRAINT_VIOLATION';
        }

        $extra = [
            'code'        => $code,
            'sql_state'   => $sqlState,
            'driver_code' => $driverCode,
        ];

        if (config('app.debug')) {
            $extra['sql']            = $e->getSql();
            $extra['bindings']       = $e->getBindings();
            $extra['driver_message'] = $driverMsg;
        }

        return $json('Database error', $status, $extra, $e);
    });

    $exceptions->render(function (\PDOException $e) use ($json) {
        return $json('Database connection error', 500, ['code' => 'DB_CONNECTION_ERROR'], $e);
    });

    $exceptions->render(function (\TypeError $e) use ($json) {
        return $json('Type error', 500, ['code' => 'TYPE_ERROR'], $e);
    });

    // Any other HttpException
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) use ($json) {
        return $json($e->getMessage() ?: 'HTTP error', $e->getStatusCode(), ['code' => 'HTTP_EXCEPTION'], $e);
    });

    // Last resort
    $exceptions->render(function (\Throwable $e) use ($json) {
        report($e);
        return $json('Server error', 500, ['code' => 'UNHANDLED_EXCEPTION'], $e);
    });
})
    ->create();
