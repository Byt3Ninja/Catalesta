<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;

final class AssignCorrelationId
{
    public function handle(Request $request, Closure $next): mixed
    {
        $id = $request->header('X-Correlation-Id') ?: CorrelationId::get();
        CorrelationId::set($id);
        $response = $next($request);
        $response->headers->set('X-Correlation-Id', $id);

        return $response;
    }
}
