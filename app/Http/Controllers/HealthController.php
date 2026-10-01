<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseIsAvailable(),
            'cache' => $this->cacheIsAvailable(),
            'storage' => is_dir(storage_path()) && is_writable(storage_path()),
        ];
        $healthy = ! in_array(false, $checks, true);

        return response()
            ->json([
                'status' => $healthy ? 'ok' : 'unavailable',
                'checks' => $checks,
            ], $healthy ? 200 : 503)
            ->header('Cache-Control', 'no-store, private');
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheIsAvailable(): bool
    {
        $key = 'health:'.Str::uuid();

        try {
            Cache::put($key, 'ok', 10);

            return Cache::get($key) === 'ok';
        } catch (Throwable) {
            return false;
        } finally {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // The failed cache check is already reflected in the response.
            }
        }
    }
}
