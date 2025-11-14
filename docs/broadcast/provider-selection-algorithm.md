# 🔄 WhatsApp Provider Selection Algorithm

**Version:** 1.0.0
**Last Updated:** November 14, 2025

---

## 📋 Overview

The WhatsApp Provider Selection Algorithm intelligently chooses the optimal WhatsApp provider (WebJS or Meta API) for each campaign based on multiple factors including campaign type, session health, availability, and user preferences.

---

## 🎯 Selection Priority Matrix

### **Primary Decision Factors**

| Factor | Weight | Description |
|--------|--------|-------------|
| **Campaign Mode** | 40% | Direct campaigns prefer WebJS, Template campaigns require Meta API |
| **Session Health** | 30% | Health score of available WhatsApp sessions |
| **User Preference** | 20% | Explicit provider preference settings |
| **Performance Metrics** | 10% | Historical delivery rates and speeds |

### **Provider Preference Hierarchy**

```
1. WhatsApp Web JS (Primary) - Direct mode campaigns
   ├── Health Score ≥ 80
   ├── Connected & Active
   └── Load balancing across sessions

2. WhatsApp Web JS (Secondary) - Backup WebJS sessions
   ├── Health Score ≥ 60
   ├── Connected & Active
   └── Secondary sessions

3. Meta API (Fallback) - Template campaigns only
   ├── Template is approved
   ├── WebJS unavailable/unhealthy
   └── Meta API configured

4. Queue for Retry - No suitable provider available
   └── Automatic retry with exponential backoff
```

---

## 🧠 Algorithm Implementation

### **Core Selection Logic**

```php
<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\WhatsAppSession;
use App\Models\Template;
use Illuminate\Support\Facades\Log;

class WhatsAppProviderSelector
{
    private const MIN_HEALTH_SCORE_PRIMARY = 80;
    private const MIN_HEALTH_SCORE_SECONDARY = 60;
    private const PROVIDER_WEIGHTS = [
        'campaign_mode' => 0.4,
        'session_health' => 0.3,
        'user_preference' => 0.2,
        'performance' => 0.1
    ];

    public function selectProvider(Campaign $campaign): array
    {
        $selectionFactors = $this->calculateSelectionFactors($campaign);

        Log::info('Provider selection factors calculated', [
            'campaign_id' => $campaign->id,
            'factors' => $selectionFactors
        ]);

        // 1. Check explicit user preference
        if ($campaign->provider_preference !== 'auto') {
            return $this->validatePreferredProvider($campaign, $campaign->provider_preference);
        }

        // 2. Template mode requires specific handling
        if ($campaign->template_mode === 'template') {
            return $this->selectTemplateProvider($campaign);
        }

        // 3. Direct mode prefers WebJS
        return $this->selectDirectMessageProvider($campaign);
    }

    private function calculateSelectionFactors(Campaign $campaign): array
    {
        return [
            'campaign_mode_score' => $this->calculateCampaignModeScore($campaign),
            'session_health_score' => $this->calculateSessionHealthScore($campaign),
            'user_preference_score' => $this->calculateUserPreferenceScore($campaign),
            'performance_score' => $this->calculatePerformanceScore($campaign),
        ];
    }

    private function calculateCampaignModeScore(Campaign $campaign): float
    {
        // Direct campaigns heavily favor WebJS (no approval needed)
        if ($campaign->template_mode === 'direct') {
            return 100.0; // Perfect score for WebJS
        }

        // Template campaigns require Meta API approval
        if ($campaign->template_mode === 'template' && $this->isTemplateApproved($campaign)) {
            return 75.0; // Good score for Meta API
        }

        return 0.0; // No suitable provider for this mode
    }

    private function calculateSessionHealthScore(Campaign $campaign): float
    {
        $sessions = $this->getAvailableSessions($campaign);

        if ($sessions->isEmpty()) {
            return 0.0;
        }

        // Weight by health score
        $totalScore = $sessions->sum('health_score');
        $averageScore = $totalScore / $sessions->count();

        // Bonus for multiple healthy sessions
        $healthyCount = $sessions->where('health_score', '>=', 70)->count();
        $multiplier = min(1.5, 1 + ($healthyCount * 0.1));

        return min(100.0, $averageScore * $multiplier);
    }

    private function calculateUserPreferenceScore(Campaign $campaign): float
    {
        if ($campaign->provider_preference === 'auto') {
            return 50.0; // Neutral score for automatic selection
        }

        $preferenceMatches = $this->checkPreferenceAvailability($campaign);

        return $preferenceMatches ? 100.0 : 0.0;
    }

    private function calculatePerformanceScore(Campaign $campaign): float
    {
        // Get historical performance data
        $workspaceId = $campaign->workspace_id;

        $webjsPerformance = $this->getProviderPerformance($workspaceId, 'webjs');
        $metaPerformance = $this->getProviderPerformance($workspaceId, 'meta');

        // Calculate weighted performance score
        $webjsScore = ($webjsPerformance['delivery_rate'] * 0.6) + ($webjsPerformance['speed'] * 0.4);
        $metaScore = ($metaPerformance['delivery_rate'] * 0.6) + ($metaPerformance['speed'] * 0.4);

        return max($webjsScore, $metaScore);
    }

    private function selectDirectMessageProvider(Campaign $campaign): array
    {
        // Priority 1: Primary WebJS sessions
        $primarySession = $this->selectPrimaryWebJSSession($campaign);
        if ($primarySession) {
            return $this->buildProviderResponse('webjs', $primarySession, 'primary_session');
        }

        // Priority 2: Secondary WebJS sessions
        $secondarySession = $this->selectSecondaryWebJSSession($campaign);
        if ($secondarySession) {
            return $this->buildProviderResponse('webjs', $secondarySession, 'secondary_session');
        }

        // Priority 3: Fallback to Meta API (if approved template available)
        $metaProvider = $this->selectMetaAPIFallback($campaign);
        if ($metaProvider) {
            return $metaProvider;
        }

        // Priority 4: Queue for retry
        return $this->buildQueueResponse();
    }

    private function selectTemplateProvider(Campaign $campaign): array
    {
        $template = $campaign->template;

        // Check if template is approved for Meta API
        if (!$template || $template->status !== 'APPROVED') {
            return $this->buildQueueResponse('template_not_approved');
        }

        // Try WebJS first (for better performance)
        $webjsSession = $this->selectOptimalWebJSSession($campaign);
        if ($webjsSession && $this->canUseWebJSForTemplate($template)) {
            return $this->buildProviderResponse('webjs', $webjsSession, 'template_webjs_fallback');
        }

        // Use Meta API (standard for templates)
        return $this->buildProviderResponse('meta_api', null, 'standard_template');
    }

    private function selectPrimaryWebJSSession(Campaign $campaign): ?WhatsAppSession
    {
        return WhatsAppSession::forWorkspace($campaign->workspace_id)
            ->connected()
            ->where('provider_type', 'webjs')
            ->where('is_active', true)
            ->where('health_score', '>=', self::MIN_HEALTH_SCORE_PRIMARY)
            ->orderByDesc('is_primary')
            ->orderByDesc('health_score')
            ->orderBy('last_activity_at', 'desc')
            ->first();
    }

    private function selectSecondaryWebJSSession(Campaign $campaign): ?WhatsAppSession
    {
        return WhatsAppSession::forWorkspace($campaign->workspace_id)
            ->connected()
            ->where('provider_type', 'webjs')
            ->where('is_active', true)
            ->where('health_score', '>=', self::MIN_HEALTH_SCORE_SECONDARY)
            ->where('is_primary', false)
            ->orderByDesc('health_score')
            ->orderBy('last_activity_at', 'desc')
            ->first();
    }

    private function selectMetaAPIFallback(Campaign $campaign): ?array
    {
        $workspace = $campaign->workspace;
        $metaConfig = $workspace->getMetaApiConfiguration();

        if (!$metaConfig || !$this->isMetaAPIConfigured($metaConfig)) {
            return null;
        }

        return $this->buildProviderResponse('meta_api', null, 'webjs_unavailable_fallback');
    }

    private function buildProviderResponse(string $type, ?WhatsAppSession $session, string $reason): array
    {
        $response = [
            'type' => $type,
            'reason' => $reason,
            'confidence' => $this->calculateConfidence($type, $session),
            'selected_at' => now()->toISOString()
        ];

        if ($session) {
            $response['session'] = [
                'id' => $session->id,
                'uuid' => $session->uuid,
                'phone_number' => $session->phone_number,
                'health_score' => $session->health_score,
                'is_primary' => $session->is_primary
            ];
        }

        return $response;
    }

    private function buildQueueResponse(string $reason = 'no_provider_available'): array
    {
        return [
            'type' => 'queue',
            'reason' => $reason,
            'confidence' => 0,
            'retry_after' => now()->addMinutes(5)->toISOString(),
            'selected_at' => now()->toISOString()
        ];
    }

    private function calculateConfidence(string $type, ?WhatsAppSession $session): int
    {
        if ($type === 'queue') {
            return 0;
        }

        if ($type === 'webjs' && $session) {
            // High confidence for healthy WebJS sessions
            $sessionScore = $session->health_score;

            if ($session->is_primary) {
                return min(100, $sessionScore + 10);
            }

            return $sessionScore;
        }

        if ($type === 'meta_api') {
            // Medium confidence for Meta API (fallback)
            return 75;
        }

        return 50; // Default confidence
    }
}
```

### **Session Health Calculation**

```php
<?php

namespace App\Services;

use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionHealthCalculator
{
    private const HEALTH_WEIGHTS = [
        'connection_stability' => 0.35,    // 35%
        'message_success_rate' => 0.25,   // 25%
        'recent_activity' => 0.20,         // 20%
        'error_rate' => -0.20,             // -20%
        'response_time' => 0.15,           // 15%
        'uptime' => 0.15,                  // 15%
    ];

    public function calculateSessionHealth(WhatsAppSession $session): int
    {
        $metrics = $this->collectSessionMetrics($session);

        $healthScore = 100; // Start with perfect score

        // Apply each health factor
        $healthScore += $metrics['connection_stability'] * self::HEALTH_WEIGHTS['connection_stability'];
        $healthScore += $metrics['message_success_rate'] * self::HEALTH_WEIGHTS['message_success_rate'];
        $healthScore += $metrics['recent_activity'] * self::HEALTH_WEIGHTS['recent_activity'];
        $healthScore += $metrics['error_rate'] * self::HEALTH_WEIGHTS['error_rate'];
        $healthScore += $metrics['response_time'] * self::HEALTH_WEIGHTS['response_time'];
        $healthScore += $metrics['uptime'] * self::HEALTH_WEIGHTS['uptime'];

        return max(0, min(100, round($healthScore)));
    }

    private function collectSessionMetrics(WhatsAppSession $session): array
    {
        $now = now();
        $oneHourAgo = $now->copy()->subHour();

        $metrics = [];

        // Connection stability (0-100)
        $metrics['connection_stability'] = $this->calculateConnectionStability($session);

        // Message success rate (0-100)
        $metrics['message_success_rate'] = $this->calculateSuccessRate($session, $oneHourAgo);

        // Recent activity (0-100)
        $metrics['recent_activity'] = $this->calculateRecentActivity($session, $oneHourAgo);

        // Error rate (0-100, negative weight)
        $metrics['error_rate'] = $this->calculateErrorRate($session, $oneHourAgo);

        // Response time (0-100)
        $metrics['response_time'] = $this->calculateResponseTime($session, $oneHourAgo);

        // Uptime percentage (0-100)
        $metrics['uptime'] = $this->calculateUptime($session);

        return $metrics;
    }

    private function calculateConnectionStability(WhatsAppSession $session): int
    {
        $status = $session->status;

        // Connection status based score
        $statusScores = [
            'connected' => 100,
            'connecting' => 50,
            'disconnected' => 20,
            'error' => 0,
        ];

        return $statusScores[$status] ?? 0;
    }

    private function calculateSuccessRate(WhatsAppSession $session, Carbon $since): int
    {
        $totalMessages = DB::table('chats')
            ->where('whatsapp_session_id', $session->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', $since)
            ->count();

        if ($totalMessages === 0) {
            return 80; // Good default for no recent activity
        }

        $successfulMessages = DB::table('chats')
            ->where('whatsapp_session_id', $session->id)
            ->where('type', 'outbound')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $since)
            ->count();

        return ($successfulMessages / $totalMessages) * 100;
    }

    private function calculateRecentActivity(WhatsAppSession $session, Carbon $since): int
    {
        $lastActivity = $session->last_activity_at;

        if (!$lastActivity) {
            return 0;
        }

        $minutesSinceActivity = $lastActivity->diffInMinutes(now());

        if ($minutesSinceActivity <= 5) {
            return 100; // Very recent activity
        } elseif ($minutesSinceActivity <= 15) {
            return 80; // Recent activity
        } elseif ($minutesSinceActivity <= 60) {
            return 60; // Moderately recent
        } else {
            return max(0, 60 - ($minutesSinceActivity - 60)); // Decrease over time
        }
    }

    private function calculateErrorRate(WhatsAppSession $session, Carbon $since): int
    {
        $totalMessages = DB::table('chats')
            ->where('whatsapp_session_id', $session->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', $since)
            ->count();

        if ($totalMessages === 0) {
            return 0; // No errors in empty period
        }

        $failedMessages = DB::table('chats')
            ->where('whatsapp_session_id', $session->id)
            ->where('type', 'outbound')
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->count();

        return ($failedMessages / $totalMessages) * 100;
    }

    private function calculateResponseTime(WhatsAppSession $session, Carbon $since): int
    {
        // Get average response time from campaign logs
        $avgResponseTime = DB::table('campaign_logs')
            ->join('chats', 'campaign_logs.chat_id', '=', 'chats.id')
            ->where('campaign_logs.whatsapp_session_id', $session->id)
            ->where('campaign_logs.created_at', '>=', $since)
            ->whereNotNull('chats.metadata->response_time_ms')
            ->avg(DB::raw('JSON_EXTRACT(chats.metadata, "$.response_time_ms")'));

        if (!$avgResponseTime) {
            return 90; // Good default if no data
        }

        // Convert to 0-100 scale (lower is better)
        $maxAcceptableTime = 5000; // 5 seconds
        return max(0, 100 - (($avgResponseTime / $maxAcceptableTime) * 100));
    }

    private function calculateUptime(WhatsAppSession $session): int
    {
        $created = $session->created_at;
        $now = now();
        $totalTime = $created->diffInSeconds($now);

        if ($totalTime === 0) {
            return 100;
        }

        // Calculate uptime from last disconnected timestamp
        $lastConnectedAt = $session->last_connected_at ?? $created;
        $disconnectedAt = $session->last_activity_at ?? $now;

        if ($session->status === 'connected') {
            $upTime = $now->diffInSeconds($lastConnectedAt);
        } else {
            $upTime = $disconnectedAt->diffInSeconds($lastConnectedAt);
        }

        return ($upTime / $totalTime) * 100;
    }
}
```

### **Load Balancing Algorithm**

```php
<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\WhatsAppSession;
use Illuminate\Support\Collection;

class LoadBalancer
{
    public function distributeMessagesAcrossSessions(
        Collection $contacts,
        Campaign $campaign
    ): array {
        $availableSessions = $this->getLoadBalancedSessions($campaign);

        if ($availableSessions->isEmpty()) {
            throw new \Exception('No available WhatsApp sessions for load balancing');
        }

        return $this->calculateOptimalDistribution($contacts, $availableSessions);
    }

    private function getLoadBalancedSessions(Campaign $campaign): Collection
    {
        $sessions = WhatsAppSession::forWorkspace($campaign->workspace_id)
            ->connected()
            ->where('is_active', true)
            ->where('health_score', '>=', 60)
            ->orderByDesc('health_score')
            ->orderBy('last_activity_at', 'desc')
            ->get();

        // Filter sessions based on campaign type
        return $this->filterSessionsByCampaignType($sessions, $campaign);
    }

    private function filterSessionsByCampaignType(Collection $sessions, Campaign $campaign): Collection
    {
        if ($campaign->template_mode === 'direct') {
            // Direct mode can use any WebJS session
            return $sessions->where('provider_type', 'webjs');
        }

        // Template mode needs session that can handle templates
        return $sessions->filter(function ($session) use ($campaign) {
            return $this->canSessionHandleTemplate($session, $campaign);
        });
    }

    private function calculateOptimalDistribution(Collection $contacts, Collection $sessions): array
    {
        $distribution = [];
        $sessionCount = $sessions->count();
        $contactCount = $contacts->count();

        // Calculate capacity for each session
        $sessionCapacities = $sessions->mapWithKeys(function ($session) use ($campaign) {
            return [
                $session->id => $this->calculateSessionCapacity($session, $campaign)
            ];
        });

        // Distribute based on weighted capacity
        $totalCapacity = $sessionCapacities->sum();
        $weights = $sessionCapacities->map(function ($capacity) use ($totalCapacity) {
            return ($capacity / $totalCapacity) * 100;
        });

        $contactIndex = 0;
        foreach ($weights as $sessionId => $weight) {
            $sessionContacts = ceil(($weight / 100) * $contactCount);

            $distribution[$sessionId] = [];

            for ($i = 0; $i < $sessionContacts && $contactIndex < $contactCount; $i++) {
                $distribution[$sessionId][] = $contacts[$contactIndex++]->id;
            }

            // Remove assigned contacts from remaining pool
            $contacts = $contacts->slice($contactIndex);
            $contactCount = $contacts->count();

            if ($contactIndex >= $contacts->count()) {
                break;
            }
        }

        return $distribution;
    }

    private function calculateSessionCapacity(WhatsAppSession $session, Campaign $campaign): int
    {
        $baseCapacity = 1000; // Base messages per hour

        // Adjust by health score
        $healthMultiplier = $session->health_score / 100;

        // Adjust by provider type
        $providerMultiplier = $session->provider_type === 'webjs' ? 1.0 : 2.0;

        // Adjust by recent performance
        $performanceMultiplier = $this->getSessionPerformanceMultiplier($session);

        // Adjust by message type
        $messageTypeMultiplier = $campaign->template_mode === 'direct' ? 1.2 : 1.0;

        return (int) ($baseCapacity * $healthMultiplier * $providerMultiplier * $performanceMultiplier * $messageTypeMultiplier);
    }

    private function getSessionPerformanceMultiplier(WhatsAppSession $session): float
    {
        // Calculate recent performance multiplier
        $recentSuccessRate = $this->getRecentSuccessRate($session);
        $recentResponseTime = $this->getRecentResponseTime($session);

        // Success rate multiplier (0.5 to 1.5)
        $successMultiplier = 0.5 + ($recentSuccessRate / 100);

        // Response time multiplier (0.5 to 1.0)
        $responseMultiplier = max(0.5, 1.0 - ($recentResponseTime / 10000));

        return ($successMultiplier + $responseMultiplier) / 2;
    }
}
```

---

## 🔄 Advanced Features

### **Dynamic Provider Switching**

```php
<?php

namespace App\Services;

class DynamicProviderSwitcher
{
    public function switchProviderDuringCampaign(
        Campaign $campaign,
        string $reason = 'performance'
    ): bool {

        // Lock campaign to prevent concurrent changes
        $lockKey = "campaign_provider_switch_{$campaign->id}";

        if (!Cache::add($lockKey, 1, 60)) {
            return false; // Already switching
        }

        try {
            $currentProvider = $this->getCurrentProvider($campaign);
            $newProvider = $this->selectOptimalProvider($campaign);

            if ($newProvider['type'] === $currentProvider['type']) {
                return true; // No change needed
            }

            // Switch provider
            $campaign->update([
                'whatsapp_session_id' => $newProvider['session']['id'] ?? null,
                'metadata' => array_merge(
                    $campaign->metadata ?? [],
                    [
                        'provider_switch' => [
                            'from' => $currentProvider['type'],
                            'to' => $newProvider['type'],
                            'reason' => $reason,
                            'timestamp' => now()->toISOString()
                        ]
                    ]
                )
            ]);

            Log::info('Provider switched during campaign', [
                'campaign_id' => $campaign->id,
                'from' => $currentProvider['type'],
                'to' => $newProvider['type'],
                'reason' => $reason
            ]);

            return true;

        } finally {
            Cache::forget($lockKey);
        }
    }

    private function getRecentSuccessRate(WhatsAppSession $session): float
    {
        // Implementation from SessionHealthCalculator
    }

    private function getRecentResponseTime(WhatsAppSession $session): float
    {
        // Implementation from SessionHealthCalculator
    }
}
```

### **Predictive Analytics**

```php
<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\WhatsAppSession;
use Illuminate\Support\Facades\DB;

class PredictiveAnalytics
{
    public function predictOptimalProvider(
        Campaign $campaign,
        int $messageCount
    ): array {

        $predictions = [];

        // Analyze historical performance
        $historicalData = $this->getHistoricalPerformance($campaign->workspace_id);

        // Predict WebJS performance
        $predictions['webjs'] = $this->predictWebJSPerformance(
            $campaign,
            $messageCount,
            $historicalData['webjs']
        );

        // Predict Meta API performance
        $predictions['meta_api'] = $this->predictMetaAPIPerformance(
            $campaign,
            $messageCount,
            $historicalData['meta_api']
        );

        // Select best prediction
        $bestProvider = array_keys($predictions, max)[0];

        return [
            'recommended_provider' => $bestProvider,
            'predictions' => $predictions,
            'confidence' => $this->calculatePredictionConfidence($predictions),
            'factors' => $this->getPredictionFactors($campaign)
        ];
    }

    private function predictWebJSPerformance(
        Campaign $campaign,
        int $messageCount,
        array $historicalData
    ): array {

        $availableSessions = $this->getAvailableWebJSSessions($campaign);
        $sessionCount = $availableSessions->count();

        if ($sessionCount === 0) {
            return [
                'available' => false,
                'estimated_delivery_rate' => 0,
                'estimated_speed' => 0,
                'confidence' => 0
            ];
        }

        $avgSessionCapacity = 1000; // messages per hour per session
        $totalCapacity = $sessionCount * $avgSessionCapacity;

        // Estimate performance based on historical data
        $estimatedDeliveryRate = $historicalData['delivery_rate'] ?? 85;
        $estimatedSpeed = min($totalCapacity, $messageCount);

        // Adjust for campaign complexity
        $complexityMultiplier = $this->getCampaignComplexityMultiplier($campaign);
        $estimatedSpeed = (int) ($estimatedSpeed * $complexityMultiplier);

        return [
            'available' => true,
            'session_count' => $sessionCount,
            'total_capacity' => $totalCapacity,
            'estimated_delivery_rate' => $estimatedDeliveryRate,
            'estimated_speed' => $estimatedSpeed,
            'estimated_time_minutes' => ceil(($messageCount / $estimatedSpeed) * 60),
            'confidence' => min(100, count($availableSessions) * 20)
        ];
    }

    private function predictMetaAPIPerformance(
        Campaign $campaign,
        int $messageCount,
        array $historicalData
    ): array {

        $isTemplateApproved = $this->isTemplateApproved($campaign);

        if (!$isTemplateApproved) {
            return [
                'available' => false,
                'reason' => 'Template not approved for Meta API',
                'confidence' => 0
            ];
        }

        // Meta API typically has higher capacity
        $baseCapacity = 5000; // messages per hour
        $estimatedSpeed = min($baseCapacity, $messageCount);

        // Use historical data for better predictions
        $estimatedDeliveryRate = $historicalData['delivery_rate'] ?? 95;
        $estimatedSpeed = (int) ($estimatedSpeed * 0.9); // Conservative estimate

        return [
            'available' => true,
            'template_approved' => true,
            'estimated_delivery_rate' => $estimatedDeliveryRate,
            'estimated_speed' => $estimatedSpeed,
            'estimated_time_minutes' => ceil(($messageCount / $estimatedSpeed) * 60),
            'confidence' => 85
        ];
    }
}
```

---

## 📊 Monitoring & Analytics

### **Provider Performance Tracking**

```php
<?php

namespace App\Services;

use App\Models\CampaignLog;
use App\Models\WhatsAppSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProviderPerformanceTracker
{
    public function getProviderPerformanceReport(
        string $workspaceId,
        Carbon $startDate,
        Carbon $endDate
    ): array {

        return [
            'webjs' => $this->getWebJSPerformance($workspaceId, $startDate, $endDate),
            'meta_api' => $this->getMetaAPIPerformance($workspaceId, $startDate, $endDate),
            'comparison' => $this->compareProviderPerformance($workspaceId, $startDate, $endDate)
        ];
    }

    private function getWebJSPerformance(string $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        $webjsSessionIds = WhatsAppSession::where('workspace_id', $workspaceId)
            ->where('provider_type', 'webjs')
            ->pluck('id');

        if ($webjsSessionIds->isEmpty()) {
            return $this->getEmptyPerformanceReport('webjs');
        }

        $metrics = DB::table('campaign_logs')
            ->join('campaigns', 'campaign_logs.campaign_id', '=', 'campaigns.id')
            ->join('chats', 'campaign_logs.chat_id', '=', 'chats.id')
            ->whereIn('campaign_logs.whatsapp_session_id', $webjsSessionIds)
            ->where('campaign_logs.created_at', '>=', $startDate)
            ->where('campaign_logs.created_at', '<=', $endDate)
            ->selectRaw('
                COUNT(*) as total_messages,
                SUM(CASE WHEN campaign_logs.status = "success" THEN 1 ELSE 0 END) as successful_messages,
                SUM(CASE WHEN chats.status = "delivered" THEN 1 ELSE 0 END) as delivered_messages,
                SUM(CASE WHEN chats.status = "read" THEN 1 ELSE 0 END) as read_messages,
                AVG(JSON_EXTRACT(chats.metadata, "$.delivery_time_ms")) as avg_delivery_time
            ')
            ->first();

        $totalMessages = $metrics->total_messages ?? 0;

        return [
            'total_messages' => $totalMessages,
            'successful_messages' => $metrics->successful_messages ?? 0,
            'delivered_messages' => $metrics->delivered_messages ?? 0,
            'read_messages' => $metrics->read_messages ?? 0,
            'failed_messages' => $totalMessages - ($metrics->successful_messages ?? 0),
            'delivery_rate' => $totalMessages > 0 ? round((($metrics->delivered_messages ?? 0) / $totalMessages) * 100, 2) : 0,
            'read_rate' => $totalMessages > 0 ? round((($metrics->read_messages ?? 0) / $totalMessages) * 100, 2) : 0,
            'success_rate' => $totalMessages > 0 ? round((($metrics->successful_messages ?? 0) / $totalMessages) * 100, 2) : 0,
            'avg_delivery_time' => $metrics->avg_delivery_time ? round($metrics->avg_delivery_time / 1000, 2) : 0
        ];
    }

    private function getMetaAPIPerformance(string $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        // Similar implementation for Meta API
        return $this->getEmptyPerformanceReport('meta_api');
    }

    private function compareProviderPerformance(string $workspaceId, Carbon $startDate, Carbon $endDate): array
    {
        $webjs = $this->getWebJSPerformance($workspaceId, $startDate, $endDate);
        $meta = $this->getMetaAPIPerformance($workspaceId, $startDate, $endDate);

        return [
            'delivery_rate_comparison' => [
                'webjs' => $webjs['delivery_rate'],
                'meta_api' => $meta['delivery_rate'],
                'winner' => $webjs['delivery_rate'] >= $meta['delivery_rate'] ? 'webjs' : 'meta_api',
                'difference' => abs($webjs['delivery_rate'] - $meta['delivery_rate'])
            ],
            'speed_comparison' => [
                'webjs' => $webjs['avg_delivery_time'],
                'meta_api' => $meta['avg_delivery_time'],
                'winner' => $webjs['avg_delivery_time'] <= $meta['avg_delivery_time'] ? 'webjs' : 'meta_api',
                'difference_seconds' => abs($webjs['avg_delivery_time'] - $meta['avg_delivery_time'])
            ]
        ];
    }

    private function getEmptyPerformanceReport(string $provider): array
    {
        return [
            'total_messages' => 0,
            'successful_messages' => 0,
            'delivered_messages' => 0,
            'read_messages' => 0,
            'failed_messages' => 0,
            'delivery_rate' => 0,
            'read_rate' => 0,
            'success_rate' => 0,
            'avg_delivery_time' => 0
        ];
    }
}
```

---

## 🎛️ Configuration Options

### **Environment Configuration**

```php
// config/whatsapp_provider.php
return [
    // Provider selection settings
    'selection' => [
        'enable_auto_switching' => env('WHATSAPP_AUTO_SWITCH', true),
        'switch_thresholds' => [
            'error_rate_threshold' => 10, // percentage
            'response_time_threshold' => 5000, // milliseconds
            'health_score_threshold' => 60,
        ],
    ],

    // Health monitoring
    'health' => [
        'check_interval' => 300, // seconds
        'min_score_primary' => 80,
        'min_score_secondary' => 60,
        'max_sessions_per_workspace' => 10,
    ],

    // Load balancing
    'load_balancing' => [
        'strategy' => 'weighted_capacity', // 'round_robin', 'weighted_capacity', 'health_score'
        'base_capacity' => 1000, // messages per hour
        'enable_predictive_analytics' => env('WHATSAPP_PREDICTIVE_ANALYTICS', true),
    ],

    // Performance tracking
    'analytics' => [
        'retention_period_days' => 30,
        'real_time_monitoring' => true,
        'alert_thresholds' => [
            'high_error_rate' => 15, // percentage
            'slow_delivery' => 10000, // milliseconds
            'low_success_rate' => 80, // percentage
        ]
    ]
];
```

---

This comprehensive provider selection algorithm ensures optimal WhatsApp message delivery by intelligently choosing between WebJS and Meta API providers based on multiple factors including campaign type, session health, historical performance, and user preferences.