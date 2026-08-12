<?php

namespace App\Providers;

use App\Support\Tenancy\OrganizationContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class, fn (): OrganizationContext => new OrganizationContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
