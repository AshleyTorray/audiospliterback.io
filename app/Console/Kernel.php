<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    // app/Console/Kernel.php

    protected $commands = [
        \App\Console\Commands\SearchNewAudioFiles::class,
        \App\Console\Commands\SearchExcelLog::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('search:new-audio-files')->everyTenSeconds();
        $schedule->command('search:excel-log')->everyTwentySeconds();
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
