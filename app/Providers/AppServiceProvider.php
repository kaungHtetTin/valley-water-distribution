<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! app()->environment('testing')) {
            DB::listen(function (QueryExecuted $query) {
                if ($query->time >= config('valley.slow_query_ms')) {
                    Log::warning('Slow database query', [
                        'time_ms' => $query->time,
                        'connection' => $query->connectionName,
                        'sql' => $query->sql,
                    ]);
                }
            });
        }
    }
}
