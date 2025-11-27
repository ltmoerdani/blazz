<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorQueueSizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:queue-size 
                            {--threshold=100 : Alert threshold for queue size}
                            {--queues= : Comma-separated list of queues to monitor}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor queue sizes and log alerts if threshold exceeded';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $queuesOption = $this->option('queues');
        
        $defaultQueues = [
            'messaging',
            'campaign-stats',
            'whatsapp-urgent',
            'whatsapp-high',
            'whatsapp-normal',
            'whatsapp-campaign',
            'default',
        ];
        
        $queues = $queuesOption 
            ? explode(',', $queuesOption) 
            : $defaultQueues;

        $this->info('Monitoring queue sizes...');
        $this->newLine();

        $headers = ['Queue', 'Pending', 'Failed', 'Status'];
        $rows = [];
        $hasWarnings = false;

        foreach ($queues as $queue) {
            $queue = trim($queue);
            $pendingCount = $this->getPendingJobsCount($queue);
            $failedCount = $this->getFailedJobsCount($queue);
            
            $status = '✅ OK';
            if ($pendingCount >= $threshold) {
                $status = '⚠️ WARNING';
                $hasWarnings = true;
                
                Log::warning('Queue size threshold exceeded', [
                    'queue' => $queue,
                    'pending_jobs' => $pendingCount,
                    'threshold' => $threshold,
                ]);
            }
            
            if ($failedCount > 0) {
                $status = $pendingCount >= $threshold ? '🚨 CRITICAL' : '⚠️ HAS FAILURES';
            }

            $rows[] = [
                $queue,
                $pendingCount,
                $failedCount,
                $status,
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();

        // Summary
        $totalPending = array_sum(array_column($rows, 1));
        $totalFailed = array_sum(array_column($rows, 2));

        $this->info("Total pending jobs: {$totalPending}");
        $this->info("Total failed jobs: {$totalFailed}");

        // Check if queue worker is running
        $workerRunning = $this->isQueueWorkerRunning();
        if ($workerRunning) {
            $this->info('Queue worker status: ✅ Running');
        } else {
            $this->error('Queue worker status: ❌ NOT RUNNING');
            Log::error('Queue worker is not running!');
        }

        if ($hasWarnings) {
            $this->warn('Some queues have exceeded the threshold!');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Get count of pending jobs for a specific queue.
     */
    private function getPendingJobsCount(string $queue): int
    {
        try {
            return DB::table('jobs')
                ->where('queue', $queue)
                ->count();
        } catch (\Exception $e) {
            Log::error('Failed to get pending jobs count', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Get count of failed jobs for a specific queue.
     */
    private function getFailedJobsCount(string $queue): int
    {
        try {
            return DB::table('failed_jobs')
                ->where('queue', $queue)
                ->count();
        } catch (\Exception $e) {
            Log::error('Failed to get failed jobs count', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Check if queue worker process is running.
     */
    private function isQueueWorkerRunning(): bool
    {
        $output = [];
        exec('pgrep -f "php artisan queue:work" 2>/dev/null', $output);
        
        return count($output) > 0;
    }
}
