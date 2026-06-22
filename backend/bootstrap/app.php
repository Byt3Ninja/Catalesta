<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\ResolveTenant;
use App\Modules\Applications\Application\Exceptions\CohortClosedException;
use App\Shared\Idempotency\Exceptions\IdempotencyConflictException;
use App\Shared\Idempotency\Exceptions\IdempotencyInFlightException;
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
        $middleware->statefulApi();
        $middleware->api(prepend: [AssignCorrelationId::class]);
        // No `login` named route exists — this is an API. Returning null from
        // redirectGuestsTo makes Authenticate throw AuthenticationException instead
        // of redirecting, which the renderer below maps to a clean 401 JSON. Without
        // this, non-JSON unauth requests (curl, browser direct nav, external clients
        // missing `Accept: application/json`) crash with RouteNotFoundException → 500.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'verified.email' => EnsureEmailVerified::class,
        ]);
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
                // Story 2.7 submit-flow mappings (these exceptions defer their HTTP
                // status to the endpoint layer by design).
                $e instanceof CohortClosedException => [422, 'COHORT_CLOSED'],
                $e instanceof IdempotencyConflictException => [422, 'IDEMPOTENCY_CONFLICT'],
                $e instanceof IdempotencyInFlightException => [409, 'IDEMPOTENCY_IN_FLIGHT'],
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
