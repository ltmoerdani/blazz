<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\SessionHealthService;
use App\Models\WhatsAppAccount;
use Illuminate\Console\Command;

/**
 * WhatsApp Health Summary Command
 *
 * Display health summary of all WhatsApp sessions
 */
class WhatsAppHealthSummaryCommand extends Command
{
    protected $signature = 'whatsapp:health-summary';
    protected $description = 'Display health summary of all WhatsApp sessions';

    public function handle()
    {
        $this->info('🏥 WhatsApp Session Health Summary');
        $this->newLine();

        $healthService = new SessionHealthService();
        $summary = $healthService->getHealthSummary();

        // Display summary table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Sessions', $summary['total']],
                ['Average Health Score', $summary['average_health_score'] . '%'],
                ['Excellent (90-100%)', $summary['excellent']],
                ['Good (70-89%)', $summary['good']],
                ['Warning (50-69%)', $summary['warning']],
                ['Critical (30-49%)', $summary['critical']],
                ['Failed (0-29%)', $summary['failed']],
            ]
        );

        $this->newLine();

        // Display detailed status of each session
        $accounts = WhatsAppAccount::whereNotNull('session_id')->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active WhatsApp sessions found.');
            return Command::SUCCESS;
        }

        $this->info('Session Details:');
        $this->newLine();

        $tableData = [];
        foreach ($accounts as $account) {
            $healthCheck = $healthService->checkSessionHealth($account);
            
            $status = match($healthCheck['health_status']) {
                'excellent' => '<fg=green>● Excellent</>',
                'good' => '<fg=cyan>● Good</>',
                'warning' => '<fg=yellow>● Warning</>',
                'critical' => '<fg=red>● Critical</>',
                'failed' => '<fg=red>● Failed</>',
                default => '● Unknown',
            };

            $tableData[] = [
                $account->phone_number ?? 'N/A',
                $account->status,
                $healthCheck['health_score'] . '%',
                $status,
                count($healthCheck['issues']) > 0 ? implode(', ', array_slice($healthCheck['issues'], 0, 2)) : 'None',
            ];
        }

        $this->table(
            ['Phone Number', 'Status', 'Health Score', 'Health Status', 'Issues'],
            $tableData
        );

        return Command::SUCCESS;
    }
}
