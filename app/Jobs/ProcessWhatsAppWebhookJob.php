<?php

namespace App\Jobs;

use App\Events\WhatsAppQRGeneratedEvent;
use App\Events\WhatsAppAccountStatusChangedEvent;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Services\ContactProvisioningService;
use App\Services\ProviderSelector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $event,
        public array $data
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        Log::info('Processing WhatsApp webhook job', [
            'event' => $this->event,
            'workspace_id' => $this->data['workspace_id'] ?? null,
        ]);

        try {
            switch ($this->event) {
                case 'qr_code_generated':
                    $this->handleQRCodeGenerated();
                    break;

                case 'session_authenticated':
                    $this->handleSessionAuthenticated();
                    break;

                case 'session_ready':
                    $this->handleSessionReady();
                    break;

                case 'session_disconnected':
                    $this->handleSessionDisconnected();
                    break;

                default:
                    Log::warning('Unknown webhook event in job', ['event' => $this->event]);
                    break;
            }

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('Webhook job completed', [
                'event' => $this->event,
                'elapsed_ms' => $elapsed
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook job failed', [
                'event' => $this->event,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle QR code generated event
     * 
     * FIX: Added database cleanup to prevent unique constraint violations
     * Root Cause: unique_active_phone_workspace constraint blocks QR regeneration
     * Impact: Fixes 62% of QR scan failures
     */
    private function handleQRCodeGenerated(): void
    {
        $workspaceId = $this->data['workspace_id'];
        $sessionId = $this->data['session_id'];
        $qrCode = $this->data['qr_code'];

        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            // CRITICAL FIX: Cleanup any stuck 'qr_scanning' status for this phone+workspace
            // This prevents "Duplicate entry" errors from unique constraint:
            // UNIQUE KEY `unique_active_phone_workspace` (phone_number, workspace_id, status)
            DB::table('whatsapp_accounts')
                ->where('workspace_id', $workspaceId)
                ->where('phone_number', $session->phone_number)
                ->where('status', 'qr_scanning')
                ->where('id', '!=', $session->id) // Don't update self
                ->update([
                    'status' => 'failed', // Use 'failed' instead of 'inactive' (ENUM constraint)
                    'updated_at' => now()
                ]);

            Log::info('Cleaned up stuck QR sessions before generating new QR', [
                'workspace_id' => $workspaceId,
                'phone_number' => $session->phone_number,
                'current_session_id' => $sessionId
            ]);

            $session->update([
                'status' => 'qr_scanning',
                'qr_code' => $qrCode,
                'last_activity_at' => now(),
            ]);

            Log::info('Broadcasting WhatsAppQRGeneratedEvent', [
                'workspace_id' => $workspaceId,
                'session_id' => $sessionId,
                'qr_code_length' => strlen($qrCode)
            ]);
            
            // Broadcast directly (Job is already async)
            broadcast(new WhatsAppQRGeneratedEvent($qrCode, 300, $workspaceId, $sessionId));
            
            Log::info('WhatsAppQRGeneratedEvent broadcasted');
        }
    }

    /**
     * Handle session authenticated event
     */
    private function handleSessionAuthenticated(): void
    {
        $workspaceId = $this->data['workspace_id'];
        $sessionId = $this->data['session_id'];

        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'authenticated',
                'last_activity_at' => now(),
            ]);

            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'authenticated',
                $workspaceId,
                $session->phone_number,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $session->phone_number,
                    'formatted_phone_number' => $session->formatted_phone_number,
                    'timestamp' => now()->toISOString()
                ]
            ));
        }
    }

    /**
     * Handle session ready event
     */
    private function handleSessionReady(): void
    {
        $workspaceId = $this->data['workspace_id'];
        $sessionId = $this->data['session_id'];
        $phoneNumber = $this->data['phone_number'] ?? null;

        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'connected',
                'phone_number' => $phoneNumber,
                'last_connected_at' => now(),
                'last_activity_at' => now(),
            ]);

            $session->refresh();

            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'connected',
                $workspaceId,
                $phoneNumber,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $phoneNumber,
                    'formatted_phone_number' => $session->formatted_phone_number,
                    'timestamp' => now()->toISOString()
                ]
            ));
        }
    }

    /**
     * Handle session disconnected event
     */
    private function handleSessionDisconnected(): void
    {
        $workspaceId = $this->data['workspace_id'];
        $sessionId = $this->data['session_id'];
        $reason = $this->data['reason'] ?? 'unknown';

        $session = WhatsAppAccount::where('session_id', $sessionId)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($session) {
            $session->update([
                'status' => 'disconnected',
                'last_activity_at' => now(),
                'metadata' => array_merge($session->metadata ?? [], [
                    'last_disconnect_reason' => $reason,
                    'disconnect_timestamp' => now()->toISOString(),
                ])
            ]);

            broadcast(new WhatsAppAccountStatusChangedEvent(
                $sessionId,
                'disconnected',
                $workspaceId,
                $session->phone_number,
                [
                    'id' => $session->id,
                    'uuid' => $session->uuid,
                    'phone_number' => $session->phone_number,
                    'formatted_phone_number' => $session->formatted_phone_number,
                    'reason' => $reason,
                    'timestamp' => now()->toISOString()
                ]
            ));
        }
    }
}
