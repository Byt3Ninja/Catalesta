<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return response()->json(['error' => [
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Email verification is required for this action.',
                'correlation_id' => CorrelationId::get(),
            ]], 403);
        }

        return $next($request);
    }
}
