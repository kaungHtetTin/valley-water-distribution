<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'api_version' => 'v1',
            'environment' => app()->environment(),
            'business_timezone' => config('platform.business_timezone'),
            'correlation_id' => $request->attributes->get('correlation_id'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
