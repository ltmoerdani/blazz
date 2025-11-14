<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppSession;
use App\Models\Campaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProviderSelectionService
{
    /**
     * Select the best WhatsApp session for sending campaign messages
     *
     * Prioritization:
     * 1. Preferred provider type (webjs > meta_api)
     * 2. Health score (>= 70)
     * 3. Primary session status
     * 4. Recent activity
     * 5. Load balancing (fewer active campaigns)
     */
    public function selectBestSession(Campaign $campaign): ?WhatsAppSession
    {
        $workspaceId = $campaign->workspace_id;
        $preferredProvider = $campaign->preferred_provider ?? 'webjs';

        // Get all active sessions for workspace
        $sessions = $this->getActiveSessions($workspaceId);

        if ($sessions->isEmpty()) {
            Log::warning('No active WhatsApp sessions found for workspace', [
                'workspace_id' => $workspaceId,
                'campaign_id' => $campaign->id
            ]);
            return null;
        }

        // Filter by preferred provider first
        $preferredSessions = $sessions->where('provider_type', $preferredProvider);

        // If no sessions with preferred provider, fallback to any provider
        if ($preferredSessions->isEmpty()) {
            Log::info('No sessions found with preferred provider, falling back to any provider', [
                'preferred_provider' => $preferredProvider,
                'workspace_id' => $workspaceId
            ]);
            $preferredSessions = $sessions;
        }

        // Score and rank sessions
        $rankedSessions = $preferredSessions->map(function ($session) use ($campaign) {
            return [
                'session' => $session,
                'score' => $this->calculateSessionScore($session, $campaign)
            ];
        })->sortByDesc('score');

        // Get best session
        $bestSessionData = $rankedSessions->first();

        if (!$bestSessionData || $bestSessionData['score'] < 50) {
            Log::warning('No healthy sessions available for campaign', [
                'campaign_id' => $campaign->id,
                'workspace_id' => $workspaceId,
                'best_score' => $bestSessionData['score'] ?? 0
            ]);
            return null;
        }

        $selectedSession = $bestSessionData['session'];

        Log::info('Selected WhatsApp session for campaign', [
            'campaign_id' => $campaign->id,
            'session_id' => $selectedSession->id,
            'provider_type' => $selectedSession->provider_type,
            'health_score' => $selectedSession->health_score,
            'final_score' => $bestSessionData['score'],
            'preferred_provider' => $preferredProvider
        ]);

        return $selectedSession;
    }

    /**
     * Get all active WhatsApp sessions for a workspace
     */
    private function getActiveSessions(int $workspaceId): Collection
    {
        return WhatsAppSession::forWorkspace($workspaceId)
            ->active()
            ->connected()
            ->withCount(['campaigns' => function ($query) {
                $query->whereIn('status', ['pending', 'scheduled', 'ongoing']);
            }])
            ->get();
    }

    /**
     * Calculate score for a session based on multiple factors
     */
    private function calculateSessionScore(WhatsAppSession $session, Campaign $campaign): int
    {
        $score = 100;

        // Health score factor (40% weight)
        $healthScore = $session->health_score;
        if ($healthScore < 70) {
            $score -= (70 - $healthScore) * 0.4; // Penalty for low health
        }

        // Provider preference factor (30% weight)
        if ($session->provider_type !== $campaign->preferred_provider) {
            $score -= 30; // Penalty for non-preferred provider
        }

        // Primary session factor (15% weight)
        if (!$session->is_primary) {
            $score -= 15;
        }

        // Recent activity factor (10% weight)
        if ($session->last_activity_at && $session->last_activity_at->lt(now()->subHour())) {
            $inactiveMinutes = $session->last_activity_at->diffInMinutes(now());
            $score -= min($inactiveMinutes * 0.1, 10); // Max 10 point penalty
        }

        // Load balancing factor (5% weight)
        $activeCampaigns = $session->campaigns_count ?? 0;
        if ($activeCampaigns > 0) {
            $score -= min($activeCampaigns * 2, 5); // Max 5 point penalty
        }

        // Provider-specific bonuses/penalties
        if ($session->provider_type === 'webjs') {
            $score += 5; // Bonus for WebJS (preferred)
        } else {
            $score -= 5; // Penalty for Meta API (fallback)
        }

        return max(0, (int) round($score));
    }

    /**
     * Check if a session is suitable for a specific campaign type
     */
    public function isSessionCompatible(WhatsAppSession $session, Campaign $campaign): bool
    {
        // Check basic connectivity
        if ($session->status !== 'connected' || !$session->is_active) {
            return false;
        }

        // Check health
        if ($session->health_score < 50) {
            return false;
        }

        // Check provider compatibility for campaign type
        if ($campaign->isTemplateBased()) {
            return $this->isTemplateCompatible($session, $campaign);
        } else {
            return $this->isDirectMessageCompatible($session, $campaign);
        }
    }

    /**
     * Check if session can handle template-based campaign
     */
    private function isTemplateCompatible(WhatsAppSession $session, Campaign $campaign): bool
    {
        if (!$campaign->template) {
            return false;
        }

        $template = $campaign->template;

        // Meta API has stricter template requirements
        if ($session->provider_type === 'meta_api') {
            // Check if template is properly approved
            if ($template->status !== 'APPROVED') {
                return false;
            }

            // Check for media compatibility
            if (in_array($template->header_type, ['image', 'document', 'video'])) {
                // Meta API has stricter media requirements
                return $template->header_media ? true : false;
            }
        }

        // WebJS is more flexible
        return true;
    }

    /**
     * Check if session can handle direct message campaign
     */
    private function isDirectMessageCompatible(WhatsAppSession $session, Campaign $campaign): bool
    {
        // WebJS is preferred for direct messages (more flexible)
        if ($session->provider_type === 'webjs') {
            return true;
        }

        // Meta API has some limitations for direct messages
        if ($session->provider_type === 'meta_api') {
            // Check if direct message has compatible content
            $messageContent = $campaign->getResolvedMessageContent();

            // Meta API may have limitations on message structure
            if ($messageContent['header_type'] === 'video' && !$messageContent['header_media']) {
                return false;
            }

            // Additional Meta API-specific checks can be added here
        }

        return true;
    }

    /**
     * Get fallback sessions in order of preference
     */
    public function getFallbackSessions(Campaign $campaign, WhatsAppSession $primarySession): Collection
    {
        $workspaceId = $campaign->workspace_id;

        return $this->getActiveSessions($workspaceId)
            ->where('id', '!=', $primarySession->id)
            ->filter(function ($session) use ($campaign) {
                return $this->isSessionCompatible($session, $campaign);
            })
            ->map(function ($session) use ($campaign) {
                return [
                    'session' => $session,
                    'score' => $this->calculateSessionScore($session, $campaign)
                ];
            })
            ->sortByDesc('score')
            ->take(3) // Top 3 fallback sessions
            ->pluck('session');
    }

    /**
     * Get provider recommendation for campaign
     */
    public function getRecommendedProvider(Campaign $campaign): array
    {
        $workspaceId = $campaign->workspace_id;
        $sessions = $this->getActiveSessions($workspaceId);

        if ($sessions->isEmpty()) {
            return [
                'recommended_provider' => 'webjs',
                'confidence' => 0,
                'reason' => 'No active sessions available'
            ];
        }

        $webjsSessions = $sessions->where('provider_type', 'webjs');
        $metaApiSessions = $sessions->where('provider_type', 'meta_api');

        $webjsScore = $webjsSessions->avg('health_score') ?? 0;
        $metaApiScore = $metaApiSessions->avg('health_score') ?? 0;

        // Template campaigns might prefer Meta API for better compliance
        if ($campaign->isTemplateBased()) {
            if ($metaApiScore >= 70 && $metaApiSessions->isNotEmpty()) {
                return [
                    'recommended_provider' => 'meta_api',
                    'confidence' => min(90, $metaApiScore + 20),
                    'reason' => 'Template campaign with healthy Meta API sessions available'
                ];
            }
        }

        // Default to WebJS preference
        if ($webjsScore >= 50) {
            return [
                'recommended_provider' => 'webjs',
                'confidence' => min(95, $webjsScore + 25),
                'reason' => 'WebJS sessions available and preferred for flexibility'
            ];
        }

        // Fallback to Meta API if WebJS unavailable
        if ($metaApiScore >= 50) {
            return [
                'recommended_provider' => 'meta_api',
                'confidence' => min(85, $metaApiScore + 15),
                'reason' => 'WebJS unavailable, Meta API fallback'
            ];
        }

        return [
            'recommended_provider' => 'webjs',
            'confidence' => 30,
            'reason' => 'Limited session availability, recommend adding more sessions'
        ];
    }

    /**
     * Analyze session health and provide recommendations
     */
    public function analyzeSessionHealth(int $workspaceId): array
    {
        $sessions = $this->getActiveSessions($workspaceId);

        if ($sessions->isEmpty()) {
            return [
                'status' => 'critical',
                'message' => 'No active WhatsApp sessions found',
                'recommendations' => [
                    'Connect at least one WhatsApp session',
                    'Ensure sessions are properly authenticated'
                ]
            ];
        }

        $healthySessions = $sessions->where('health_score', '>=', 70);
        $webjsSessions = $sessions->where('provider_type', 'webjs');
        $metaApiSessions = $sessions->where('provider_type', 'meta_api');

        $analysis = [
            'total_sessions' => $sessions->count(),
            'healthy_sessions' => $healthySessions->count(),
            'webjs_sessions' => $webjsSessions->count(),
            'meta_api_sessions' => $metaApiSessions->count(),
            'avg_health_score' => round($sessions->avg('health_score'), 1),
        ];

        // Determine status
        if ($healthySessions->count() >= 2 && $webjsSessions->isNotEmpty()) {
            $analysis['status'] = 'excellent';
            $analysis['message'] = 'Multiple healthy sessions available with preferred WebJS provider';
        } elseif ($healthySessions->count() >= 1) {
            $analysis['status'] = 'good';
            $analysis['message'] = 'At least one healthy session available';
        } else {
            $analysis['status'] = 'warning';
            $analysis['message'] = 'Sessions available but health is below optimal';
        }

        // Generate recommendations
        $analysis['recommendations'] = $this->generateHealthRecommendations($analysis, $sessions);

        return $analysis;
    }

    /**
     * Generate health recommendations based on analysis
     */
    private function generateHealthRecommendations(array $analysis, Collection $sessions): array
    {
        $recommendations = [];

        if ($analysis['webjs_sessions'] === 0) {
            $recommendations[] = 'Add a WhatsApp Web JS session for better compatibility';
        }

        if ($analysis['healthy_sessions'] < $analysis['total_sessions']) {
            $recommendations[] = 'Check and reconnect sessions with low health scores';
        }

        if ($analysis['total_sessions'] < 2) {
            $recommendations[] = 'Consider adding multiple sessions for load balancing and redundancy';
        }

        $inactiveSessions = $sessions->filter(function ($session) {
            return !$session->last_activity_at || $session->last_activity_at->lt(now()->subHours(6));
        });

        if ($inactiveSessions->isNotEmpty()) {
            $recommendations[] = 'Reactivate inactive sessions to maintain availability';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Session configuration is optimal';
        }

        return $recommendations;
    }
}