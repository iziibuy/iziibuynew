<?php

namespace App\Console;

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
        $schedule->command('subscription:notify')->monthlyOn(27);
        $schedule->command('monthly:charge 1')->monthlyOn(1);
        $schedule->command('monthly:charge 1')->monthlyOn(3);
        $schedule->command('monthly:charge 0')->monthlyOn(5);
        $schedule->command('enterprise:payment 1')->monthlyOn(1);
        $schedule->command('enterprise:payment 1')->monthlyOn(3);
        $schedule->command('enterprise:payment 0')->monthlyOn(5);
        $schedule->command('credits:expire')->daily();
        $schedule->command('bonus:calculate')->monthlyOn(now()->endOfMonth()->format('d') - 1, '23:59');
        $schedule->command('booking:complete')->everyFifteenMinutes();
        $schedule->command('shops:remove')->everyFifteenMinutes();
        $schedule->command('queue:work --stop-when-empty')->everyMinute();
        $schedule->command('subscription:autorenew')->daily();
        $schedule->command('payment-method-access:charge')->daily();
        $schedule->command('external-button-subscriptions:charge')->daily();
        $schedule->command('order:cancel')->everyMinute();
        $schedule->command('charges:check')->everyFiveMinutes();
        $schedule->command('shops:process')->hourly();

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
