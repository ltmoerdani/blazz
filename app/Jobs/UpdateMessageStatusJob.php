<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Contact;
use App\Events\MessageStatusUpdated;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\TypingIndicator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateMessageStatusJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $messageId,
        public string $status,
        public ?int $recipientId = null,
        public ?int $ackLevel = null,
        public ?string $eventType = null
    ) {
        $this->onQueue('messaging');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $chat = Chat::where('whatsapp_message_id', $this->messageId)->first();

            if (!$chat) {
                Log::warning('Chat not found for status update', [
                    'whatsapp_message_id' => $this->messageId,
                    'status' => $this->status
                ]);
                return;
            }

            // Update chat status and timestamps in database
            $updateData = [
                'message_status' => $this->status,
                'ack_level' => $this->ackLevel,
            ];

            // Add specific timestamps based on status
            if ($this->status === 'delivered') {
                $updateData['delivered_at'] = now();
            } elseif ($this->status === 'read') {
                $updateData['read_at'] = now();
            }

            $chat->update($updateData);

            // Update contact last activity
            $contact = $chat->contact;
            if ($contact) {
                $contact->update([
                    'last_message_at' => now(),
                    'last_activity' => now()
                ]);
            }

            Log::info('Message status updated successfully', [
                'chat_id' => $chat->id,
                'whatsapp_message_id' => $this->messageId,
                'status' => $this->status,
                'ack_level' => $this->ackLevel
            ]);

            // Broadcast real-time event based on event type
            switch ($this->eventType) {
                case 'message_delivered':
                    MessageDelivered::dispatch($chat, $this->recipientId ?? $contact->id, $this->messageId);
                    break;

                case 'message_read':
                    MessageRead::dispatch($chat, $this->recipientId ?? $contact->id, $this->messageId);
                    break;

                default:
                    MessageStatusUpdated::dispatch($chat, $this->status, $this->recipientId);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('Failed to update message status', [
                'whatsapp_message_id' => $this->messageId,
                'status' => $this->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateMessageStatusJob failed permanently', [
            'message_id' => $this->messageId,
            'status' => $this->status,
            'event_type' => $this->eventType,
            'attempt' => $this->attempts(),
            'exception' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]
        ]);

        // Try to find the chat and mark it as failed
        try {
            $chat = \App\Models\Chat::where('whatsapp_message_id', $this->messageId)->first();

            if ($chat) {
                $chat->update([
                    'message_status' => 'failed',
                    'retry_count' => ($chat->retry_count ?? 0) + 1,
                    'metadata' => array_merge(
                        $chat->metadata ? json_decode($chat->metadata, true) : [],
                        [
                            'job_error' => $exception->getMessage(),
                            'job_failed_at' => now()->toISOString(),
                            'job_attempts' => $this->attempts()
                        ]
                    )
                ]);

                Log::info('Marked message as failed due to job failure', [
                    'chat_id' => $chat->id,
                    'whatsapp_message_id' => $this->messageId
                ]);
            }
        } catch (\Exception $e) {
            Log::critical('Failed to mark chat as failed after job failure', [
                'message_id' => $this->messageId,
                'job_error' => $exception->getMessage(),
                'additional_error' => $e->getMessage()
            ]);
        }

        // Optionally notify administrators
        $this->notifyAdministratorsOnFailure($exception);
    }

    /**
     * Notify administrators about critical job failures
     */
    private function notifyAdministratorsOnFailure(\Throwable $exception): void
    {
        // Only notify for specific error types or after multiple attempts
        $shouldNotify = $this->attempts() >= 2 ||
                        str_contains($exception->getMessage(), 'Connection') ||
                        str_contains($exception->getMessage(), 'Authentication') ||
                        str_contains($exception->getMessage(), 'API');

        if ($shouldNotify) {
            // You can implement notification logic here
            // For example: send email, Slack notification, etc.
            Log::critical('Critical message status update job failure', [
                'message_id' => $this->messageId,
                'attempts' => $this->attempts(),
                'requires_admin_attention' => true
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['messaging', 'message-status', "message:{$this->messageId}"];
    }
}
