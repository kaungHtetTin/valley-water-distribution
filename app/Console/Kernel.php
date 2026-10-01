<?php

namespace App\Console;

use App\Http\Controllers\Api\KpiReviewController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('valley:backup')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
        $schedule->call(fn () => app(KpiReviewController::class)->ensureMonthlyReviews(now()->format('Y-m'), refreshExisting: true))
            ->name('generate-monthly-kpi-reviews')
            ->dailyAt('00:05')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
