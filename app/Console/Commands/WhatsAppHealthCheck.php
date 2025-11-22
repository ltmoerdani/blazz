<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\HealthMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WhatsAppHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:health-check
                            {--instance= : Check specific instance by index}
                            {--summary : Show only summary}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check health of WhatsApp service instances';

    /**
     * Execute the console command.
     */
    public function handle(HealthMonitor $healthMonitor)
    {
        try {
            if ($this->option('instance') !== null) {
                // Check specific instance
                $instanceIndex = (int) $this->option('instance');
                $health = $healthMonitor->checkInstance($instanceIndex);

                if (!$health) {
                    $this->error("Instance {$instanceIndex} not found in configuration");
                    return 1;
                }

                if ($this->option('json')) {
                    $this->line(json_encode($health, JSON_PRETTY_PRINT));
                } else {
                    $this->displayInstanceHealth($instanceIndex, $health);
                }
            } else {
                // Check all instances
                $summary = $healthMonitor->getHealthSummary();

                if ($this->option('summary')) {
                    if ($this->option('json')) {
                        $this->line(json_encode($summary, JSON_PRETTY_PRINT));
                    } else {
                        $this->displaySummary($summary);
                    }
                } else {
                    $health = $healthMonitor->checkAllInstances();

                    if ($this->option('json')) {
                        $this->line(json_encode([
                            'instances' => $health,
                            'summary' => $summary
                        ], JSON_PRETTY_PRINT));
                    } else {
                        $this->displayAllHealth($health, $summary);
                    }
                }
            }

            // Log health status for monitoring
            Log::info('WhatsApp health check completed', [
                'summary' => $summary ?? null,
                'command_options' => $this->options()
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("Health check failed: " . $e->getMessage());
            Log::error('WhatsApp health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Display health for a specific instance.
     */
    private function displayInstanceHealth(int $index, array $health)
    {
        $status = $health['status'];
        $statusIcon = $this->getStatusIcon($status);

        $this->info("WhatsApp Instance {$index} ({$health['url']})");
        $this->line("  Status: {$statusIcon} {$status}");

        if (isset($health['sessions'])) {
            $this->line("  Sessions: {$health['sessions']}");
        }
        if (isset($health['utilization'])) {
            $this->line("  Utilization: {$health['utilization']}%");
        }
        if (isset($health['response_time_ms'])) {
            $this->line("  Response Time: {$health['response_time_ms']}ms");
        }
        if (isset($health['error'])) {
            $this->line("  Error: {$health['error']}");
        }
    }

    /**
     * Display health for all instances.
     */
    private function displayAllHealth(array $health, array $summary)
    {
        $this->info('WhatsApp Instance Health Check');
        $this->line(str_repeat('=', 50));

        foreach ($health as $index => $instanceHealth) {
            $status = $instanceHealth['status'];
            $statusIcon = $this->getStatusIcon($status);

            $this->line("Instance {$index}: {$statusIcon} {$status} ({$instanceHealth['url']})");

            if (isset($instanceHealth['sessions'])) {
                $this->line("  Sessions: {$instanceHealth['sessions']}");
            }
            if (isset($instanceHealth['utilization'])) {
                $utilizationBar = $this->getProgressBar($instanceHealth['utilization'], 20);
                $this->line("  Utilization: {$instanceHealth['utilization']}% [{$utilizationBar}]");
            }
            if (isset($instanceHealth['response_time_ms'])) {
                $this->line("  Response Time: {$instanceHealth['response_time_ms']}ms");
            }
            if (isset($instanceHealth['error'])) {
                $this->line("  Error: {$instanceHealth['error']}");
            }
            $this->line('');
        }

        $this->displaySummary($summary);
    }

    /**
     * Display health summary.
     */
    private function displaySummary(array $summary)
    {
        $statusIcon = $this->getStatusIcon($summary['overall_status']);

        $this->info('Summary');
        $this->line(str_repeat('-', 30));
        $this->line("Overall Status: {$statusIcon} {$summary['overall_status']}");
        $this->line("Instances: {$summary['healthy_instances']}/{$summary['total_instances']} healthy");
        $this->line("Total Sessions: {$summary['total_sessions']}");
        $this->line("Average Utilization: {$summary['average_utilization']}%");

        $capacityBar = $this->getProgressBar($summary['capacity_used_percent'], 30);
        $this->line("Capacity Used: {$summary['capacity_used_percent']}% [{$capacityBar}]");

        if (!empty($summary['unhealthy_instances'])) {
            $this->line("Unhealthy Instances: " . implode(', ', $summary['unhealthy_instances']));
        }
    }

    /**
     * Get status icon for display.
     */
    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => '✅',
            'degraded' => '⚠️',
            'unhealthy' => '❌',
            'unreachable' => '🔌',
            default => '❓',
        };
    }

    /**
     * Create a simple progress bar.
     */
    private function getProgressBar(float $percent, int $length = 20): string
    {
        $filled = (int) round(($percent / 100) * $length);
        $empty = $length - $filled;

        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }
}
