<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\WhatsApp\ProviderSelector;
use Illuminate\Console\Command;

class MonitorWhatsAppProviders extends Command
{
    protected $signature = 'whatsapp:monitor-providers';
    protected $description = 'Monitor WhatsApp provider health and update workspace metadata';

    public function handle(ProviderSelector $selector)
    {
        $workspaces = Workspace::whereNotNull('metadata')->get();

        foreach ($workspaces as $workspace) {
            $metadata = $workspace->metadata ? json_decode($workspace->metadata, true) : [];
            if (!isset($metadata['whatsapp'])) {
                continue;
            }

            $current = $selector->selectProvider($workspace);
            $previous = $metadata['whatsapp']['active_provider'] ?? null;

            if ($current !== $previous) {
                $metadata['whatsapp']['active_provider'] = $current;
                $metadata['whatsapp']['provider_changed_at'] = now()->toIso8601String();
                $workspace->metadata = json_encode($metadata);
                $workspace->save();

                $this->info("Workspace {$workspace->id}: Provider changed from {$previous} to {$current}");
            }
        }

        return Command::SUCCESS;
    }
}
