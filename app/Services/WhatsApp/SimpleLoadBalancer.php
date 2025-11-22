<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Simple Load Balancer for WhatsApp Instances
 * 
 * Strategy: Round-robin based on fewest active sessions per instance.
 * No health monitoring. No session migration. Keep it simple.
 */
class SimpleLoadBalancer
{
    /**
     * Available WhatsApp instances
     */
    protected array $instances = [
        'http://localhost:3001',
        'http://localhost:3002',
        'http://localhost:3003',
        'http://localhost:3004',
    ];

    /**
     * Get instance with minimum active sessions
     * 
     * @return string Instance URL
     */
    public function getNextInstance(): string
    {
        // Get session count per instance (only active sessions)
        $distribution = DB::table('whatsapp_accounts')
            ->select('assigned_instance_url', DB::raw('COUNT(*) as count'))
            ->whereIn('status', ['connected', 'qr_scanning'])
            ->groupBy('assigned_instance_url')
            ->pluck('count', 'assigned_instance_url')
            ->toArray();

        // Find instance with minimum sessions
        $minCount = PHP_INT_MAX;
        $selectedInstance = $this->instances[0]; // Default to first instance

        foreach ($this->instances as $instanceUrl) {
            $count = $distribution[$instanceUrl] ?? 0;

            if ($count < $minCount) {
                $minCount = $count;
                $selectedInstance = $instanceUrl;
            }
        }

        Log::info('SimpleLoadBalancer: Selected instance', [
            'selected' => $selectedInstance,
            'session_count' => $minCount,
            'distribution' => $distribution,
        ]);

        return $selectedInstance;
    }

    /**
     * Get current session distribution across all instances
     * 
     * @return array Distribution data
     */
    public function getDistribution(): array
    {
        $distribution = [];

        foreach ($this->instances as $instanceUrl) {
            $count = WhatsAppAccount::where('assigned_instance_url', $instanceUrl)
                ->whereIn('status', ['connected', 'qr_scanning'])
                ->count();

            $distribution[] = [
                'instance_url' => $instanceUrl,
                'session_count' => $count,
            ];
        }

        return $distribution;
    }

    /**
     * Check if load is balanced (variance < 20%)
     * 
     * @return bool True if balanced
     */
    public function isBalanced(): bool
    {
        $distribution = $this->getDistribution();
        $counts = array_column($distribution, 'session_count');

        if (empty($counts)) {
            return true; // No sessions = balanced
        }

        $max = max($counts);
        $min = min($counts);
        $avg = array_sum($counts) / count($counts);

        // Calculate variance percentage
        $variance = ($max - $min) / ($avg ?: 1) * 100;

        return $variance < 20; // Less than 20% variance = balanced
    }
}
