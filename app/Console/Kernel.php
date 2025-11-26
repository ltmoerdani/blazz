<?php

namespace App\Console;

use App\Jobs\CreateCampaignLogsJob;
use App\Jobs\ProcessCampaignMessagesJob;
use App\Models\CampaignLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new CreateCampaignLogsJob(), 'campaign-logs')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(new ProcessCampaignMessagesJob(), 'campaign-messages')
            ->everyMinute()
            ->withoutOverlapping();
        
        // Monitor queue health
        $schedule->command('queue:restart')
            ->hourly()
            ->evenInMaintenanceMode();
        
        // Clean failed jobs table
        $schedule->command('queue:prune-failed --hours=24')
            ->daily()
            ->evenInMaintenanceMode();

        $schedule->command('queue:prune-batches --hours=48 --unfinished=72')
            ->daily();
        
        $schedule->command('model:prune', [
            '--model' => [CampaignLog::class],
            '--hours' => 72,
        ])->daily();

        // Monitor queue size
        $schedule->command('monitor:queue-size')
            ->everyFiveMinutes();
        
        // ✅ PHASE 1: Sync WhatsApp instance URLs proactively
        $schedule->command('whatsapp:sync-instance-urls')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('whatsapp:sync-instance-urls command failed');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require_once base_path('routes/console.php');
    }
}
