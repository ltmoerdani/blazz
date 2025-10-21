<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class WhatsAppProviderNotFoundException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'No working WhatsApp providers available for this workspace', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     */
    public function report(): bool
    {
        // Log the exception or send notifications
        \Illuminate\Support\Facades\Log::warning('WhatsApp provider not found', [
            'message' => $this->getMessage(),
            'workspace_id' => $this->getWorkspaceIdFromContext(),
        ]);

        return false;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render($request)
    {
        // Parameter required by Laravel's exception handler interface
        // but not used in this specific implementation
        unset($request);

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_type' => 'whatsapp_provider_not_found'
        ], 404);
    }

    /**
     * Get workspace ID from context if available
     */
    private function getWorkspaceIdFromContext(): ?int
    {
        try {
            return session('current_workspace');
        } catch (\Exception $e) {
            return null;
        }
    }
}
