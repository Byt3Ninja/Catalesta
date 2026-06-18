<?php

declare(strict_types=1);

namespace App\StartupGateMock\Http;

use App\StartupGateMock\Support\MockKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class JwksController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(MockKeys::jwks());
    }
}
