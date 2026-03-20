<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Example scheduled tasks for Diecast Empire
        
        // Clean up expired loyalty credits daily at 2 AM
        $schedule->command('loyalty:cleanup-expired')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        // Send pre-order arrival notifications daily at 9 AM
        $schedule->command('preorders:send-arrival-notifications')
                 ->dailyAt('09:00')
                 ->withoutOverlapping();

        // Update product popularity scores hourly
        $schedule->command('products:update-popularity')
                 ->hourly()
                 ->withoutOverlapping();

        // Generate daily sales reports at 1 AM
        $schedule->command('reports:generate-daily-sales')
                 ->dailyAt('01:00')
                 ->withoutOverlapping();

        // Clean up abandoned carts older than 7 days
        $schedule->command('cart:cleanup-abandoned')
                 ->daily()
                 ->withoutOverlapping();

        // Send low stock alerts to admins daily at 8 AM
        $schedule->command('inventory:send-low-stock-alerts')
                 ->dailyAt('08:00')
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}