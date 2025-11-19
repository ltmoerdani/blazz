<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignStatisticsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $campaignId,
        public int $workspaceId,
        public string $campaignUuid,
        public array $statistics
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Workspace-wide channel (all users in workspace)
            new Channel("workspace.{$this->workspaceId}"),
            
            // Campaign-specific channel (users viewing this campaign)
            new Channel("campaign.{$this->campaignUuid}")
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'campaign.statistics.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign_id' => $this->campaignId,
            'campaign_uuid' => $this->campaignUuid,
            'workspace_id' => $this->workspaceId,
            'statistics' => $this->statistics,
            'timestamp' => now()->toISOString()
        ];
    }
}
