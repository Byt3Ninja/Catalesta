<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Shared\Support\CorrelationId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AssignCorrelationId::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $code] = match (true) {
                $e instanceof ValidationException => [422, 'VALIDATION_ERROR'],
                $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED'],
                $e instanceof AuthorizationException => [403, 'FORBIDDEN'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'HTTP_'.$e->getStatusCode()],
                default => [500, 'SERVER_ERROR'],
            };

            $payload = ['error' => [
                'code' => $code,
                'message' => $status === 500 ? 'Server error.' : $e->getMessage(),
                'correlation_id' => CorrelationId::get(),
            ]];

            if ($e instanceof ValidationException) {
                $payload['error']['details'] = $e->errors();
            }

            return response()->json($payload, $status);
        });
    })->create();
