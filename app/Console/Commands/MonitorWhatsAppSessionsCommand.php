<?php

namespace App\Console\Commands;

use App\Jobs\MonitorWhatsAppSessionHealthJob;
use Illuminate\Console\Command;

/**
 * Monitor WhatsApp Sessions Command
 *
 * Manual command to trigger session health monitoring.
 * Normally runs automatically via scheduler.
 */
class MonitorWhatsAppSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:monitor-sessions
                            {--force : Force monitoring even if recently checked}
                            {--reconnect : Auto-reconnect unhealthy sessions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor WhatsApp session health and trigger auto-reconnect if needed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏥 Starting WhatsApp Session Health Monitor...');
        $this->newLine();

        try {
            // Dispatch the job synchronously so we can see the output
            MonitorWhatsAppSessionHealthJob::dispatchSync();

            $this->newLine();
            $this->info('✅ Session health monitoring completed successfully!');
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to monitor sessions: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}
