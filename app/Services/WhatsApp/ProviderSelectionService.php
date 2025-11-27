<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Models\Campaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProviderSelectionService
{
    private $workspaceId;

    public function __construct($workspaceId = null)
    {
        // Backward compatible: fallback to session if not provided
        $this->workspaceId = $workspaceId ?? session('current_workspace');
    }

    /**
     * Select the best WhatsApp account for sending campaign messages
     *
     * Prioritization:
     * 1. Preferred provider type (webjs > meta_api)
     * 2. Health score (>= 70)
     * 3. Primary account status
     * 4. Recent activity
     * 5. Load balancing (fewer active campaigns)
     */
    public function selectBestAccount(Campaign $campaign): ?WhatsAppAccount
    {
        $workspaceId = $campaign->workspace_id;
        $preferredProvider = $campaign->preferred_provider ?? 'webjs';

        // Get all active accounts for workspace
        $accounts = $this->getActiveAccounts($workspaceId);

        if ($accounts->isEmpty()) {
            Log::warning('No active WhatsApp accounts found for workspace', [
                'workspace_id' => $workspaceId,
                'campaign_id' => $campaign->id
            ]);
            return null;
        }

        // Filter by preferred provider first
        $preferredAccounts = $accounts->where('provider_type', $preferredProvider);

        // If no accounts with preferred provider, fallback to any provider
        if ($preferredAccounts->isEmpty()) {
            Log::info('No accounts found with preferred provider, falling back to any provider', [
                'preferred_provider' => $preferredProvider,
                'workspace_id' => $workspaceId
            ]);
            $preferredAccounts = $accounts;
        }

        // Score and rank accounts
        $rankedAccounts = $preferredAccounts->map(function ($account) use ($campaign) {
            return [
                'account' => $account,
                'score' => $this->calculateAccountScore($account, $campaign)
            ];
        })->sortByDesc('score');

        // Get best account
        $bestAccountData = $rankedAccounts->first();

        if (!$bestAccountData || $bestAccountData['score'] < 50) {
            Log::warning('No healthy accounts available for campaign', [
                'campaign_id' => $campaign->id,
                'workspace_id' => $workspaceId,
                'best_score' => $bestAccountData['score'] ?? 0
            ]);
            return null;
        }

        $selectedAccount = $bestAccountData['account'];

        Log::info('Selected WhatsApp account for campaign', [
            'campaign_id' => $campaign->id,
            'account_id' => $selectedAccount->id,
            'provider_type' => $selectedAccount->provider_type,
            'health_score' => $selectedAccount->health_score,
            'final_score' => $bestAccountData['score'],
            'preferred_provider' => $preferredProvider
        ]);

        return $selectedAccount;
    }

    /**
     * Get all active WhatsApp accounts for a workspace
     */
    private function getActiveAccounts(int $workspaceId): Collection
    {
        return WhatsAppAccount::forWorkspace($workspaceId)
            ->active()
            ->connected()
            ->withCount(['campaigns' => function ($query) {
                $query->whereIn('status', ['pending', 'scheduled', 'ongoing']);
            }])
            ->get();
    }

    /**
     * Calculate score for an account based on multiple factors
     */
    private function calculateAccountScore(WhatsAppAccount $account, Campaign $campaign): int
    {
        $score = 100;

        // Health score factor (40% weight)
        $healthScore = $account->health_score;
        if ($healthScore < 70) {
            $score -= (70 - $healthScore) * 0.4; // Penalty for low health
        }

        // Provider preference factor (30% weight)
        if ($account->provider_type !== $campaign->preferred_provider) {
            $score -= 30; // Penalty for non-preferred provider
        }

        // Primary account factor (15% weight)
        if (!$account->is_primary) {
            $score -= 15;
        }

        // Recent activity factor (10% weight)
        if ($account->last_activity_at && $account->last_activity_at->lt(now()->subHour())) {
            $inactiveMinutes = $account->last_activity_at->diffInMinutes(now());
            $score -= min($inactiveMinutes * 0.1, 10); // Max 10 point penalty
        }

        // Load balancing factor (5% weight)
        $activeCampaigns = $account->campaigns_count ?? 0;
        if ($activeCampaigns > 0) {
            $score -= min($activeCampaigns * 2, 5); // Max 5 point penalty
        }

        // Provider-specific bonuses/penalties
        if ($account->provider_type === 'webjs') {
            $score += 5; // Bonus for WebJS (preferred)
        } else {
            $score -= 5; // Penalty for Meta API (fallback)
        }

        return max(0, (int) round($score));
    }

    /**
     * Check if an account is suitable for a specific campaign type
     */
    public function isAccountCompatible(WhatsAppAccount $account, Campaign $campaign): bool
    {
        // Check basic connectivity
        if ($account->status !== 'connected' || !$account->is_active) {
            return false;
        }

        // Check health
        if ($account->health_score < 50) {
            return false;
        }

        // Check provider compatibility for campaign type
        if ($campaign->isTemplateBased()) {
            return $this->isTemplateCompatible($account, $campaign);
        } else {
            return $this->isDirectMessageCompatible($account, $campaign);
        }
    }

    /**
     * Check if account can handle template-based campaign
     */
    private function isTemplateCompatible(WhatsAppAccount $account, Campaign $campaign): bool
    {
        if (!$campaign->template) {
            return false;
        }

        $template = $campaign->template;

        // Meta API has stricter template requirements
        if ($account->provider_type === 'meta_api') {
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
     * Check if account can handle direct message campaign
     */
    private function isDirectMessageCompatible(WhatsAppAccount $account, Campaign $campaign): bool
    {
        // WebJS is preferred for direct messages (more flexible)
        if ($account->provider_type === 'webjs') {
            return true;
        }

        // Meta API has some limitations for direct messages
        if ($account->provider_type === 'meta_api') {
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
     * Get fallback accounts in order of preference
     */
    public function getFallbackAccounts(Campaign $campaign, WhatsAppAccount $primaryAccount): Collection
    {
        $workspaceId = $campaign->workspace_id;

        return $this->getActiveAccounts($workspaceId)
            ->where('id', '!=', $primaryAccount->id)
            ->filter(function ($account) use ($campaign) {
                return $this->isAccountCompatible($account, $campaign);
            })
            ->map(function ($account) use ($campaign) {
                return [
                    'account' => $account,
                    'score' => $this->calculateAccountScore($account, $campaign)
                ];
            })
            ->sortByDesc('score')
            ->take(3) // Top 3 fallback accounts
            ->pluck('account');
    }

    /**
     * Get provider recommendation for campaign
     */
    public function getRecommendedProvider(Campaign $campaign): array
    {
        $workspaceId = $campaign->workspace_id;
        $accounts = $this->getActiveAccounts($workspaceId);

        if ($accounts->isEmpty()) {
            return [
                'recommended_provider' => 'webjs',
                'confidence' => 0,
                'reason' => 'No active accounts available'
            ];
        }

        $webjsAccounts = $accounts->where('provider_type', 'webjs');
        $metaApiAccounts = $accounts->where('provider_type', 'meta_api');

        $webjsScore = $webjsAccounts->avg('health_score') ?? 0;
        $metaApiScore = $metaApiAccounts->avg('health_score') ?? 0;

        // Template campaigns might prefer Meta API for better compliance
        if ($campaign->isTemplateBased()) {
            if ($metaApiScore >= 70 && $metaApiAccounts->isNotEmpty()) {
                return [
                    'recommended_provider' => 'meta_api',
                    'confidence' => min(90, $metaApiScore + 20),
                    'reason' => 'Template campaign with healthy Meta API accounts available'
                ];
            }
        }

        // Default to WebJS preference
        if ($webjsScore >= 50) {
            return [
                'recommended_provider' => 'webjs',
                'confidence' => min(95, $webjsScore + 25),
                'reason' => 'WebJS accounts available and preferred for flexibility'
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
            'reason' => 'Limited account availability, recommend adding more accounts'
        ];
    }

    /**
     * Analyze account health and provide recommendations
     */
    public function analyzeAccountHealth(int $workspaceId): array
    {
        $accounts = $this->getActiveAccounts($workspaceId);

        if ($accounts->isEmpty()) {
            return [
                'status' => 'critical',
                'message' => 'No active WhatsApp accounts found',
                'recommendations' => [
                    'Connect at least one WhatsApp account',
                    'Ensure accounts are properly authenticated'
                ]
            ];
        }

        $healthyAccounts = $accounts->where('health_score', '>=', 70);
        $webjsAccounts = $accounts->where('provider_type', 'webjs');
        $metaApiAccounts = $accounts->where('provider_type', 'meta_api');

        $analysis = [
            'total_accounts' => $accounts->count(),
            'healthy_accounts' => $healthyAccounts->count(),
            'webjs_accounts' => $webjsAccounts->count(),
            'meta_api_accounts' => $metaApiAccounts->count(),
            'avg_health_score' => round($accounts->avg('health_score'), 1),
        ];

        // Determine status
        if ($healthyAccounts->count() >= 2 && $webjsAccounts->isNotEmpty()) {
            $analysis['status'] = 'excellent';
            $analysis['message'] = 'Multiple healthy accounts available with preferred WebJS provider';
        } elseif ($healthyAccounts->count() >= 1) {
            $analysis['status'] = 'good';
            $analysis['message'] = 'At least one healthy account available';
        } else {
            $analysis['status'] = 'warning';
            $analysis['message'] = 'Accounts available but health is below optimal';
        }

        // Generate recommendations
        $analysis['recommendations'] = $this->generateHealthRecommendations($analysis, $accounts);

        return $analysis;
    }

    /**
     * Generate health recommendations based on analysis
     */
    private function generateHealthRecommendations(array $analysis, Collection $accounts): array
    {
        $recommendations = [];

        if ($analysis['webjs_accounts'] === 0) {
            $recommendations[] = 'Add a WhatsApp Web JS account for better compatibility';
        }

        if ($analysis['healthy_accounts'] < $analysis['total_accounts']) {
            $recommendations[] = 'Check and reconnect accounts with low health scores';
        }

        if ($analysis['total_accounts'] < 2) {
            $recommendations[] = 'Consider adding multiple accounts for load balancing and redundancy';
        }

        $inactiveAccounts = $accounts->filter(function ($account) {
            return !$account->last_activity_at || $account->last_activity_at->lt(now()->subHours(6));
        });

        if ($inactiveAccounts->isNotEmpty()) {
            $recommendations[] = 'Reactivate inactive accounts to maintain availability';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Account configuration is optimal';
        }

        return $recommendations;
    }
}