# 🔐 WhatsApp account Management Guide

**Version:** 1.0.0
**Last Updated:** November 14, 2025

---

## 📋 Overview

WhatsApp account Management handles the lifecycle of WhatsApp connections, including WebJS sessions and Meta API configurations. This guide covers session creation, health monitoring, failover logic, and optimization strategies.

---

## 🏗️ Session Architecture

### **Session Types**

#### **1. WhatsApp Web JS Sessions**
- **Connection Method**: Direct WhatsApp Web browser automation
- **Approval Required**: No
- **Session Duration**: Limited by WhatsApp Web app (typically 24-48 hours)
- **Scalability**: Multiple sessions per workspace
- **Cost**: Free (no per-message fees)

#### **2. Meta API Sessions**
- **Connection Method**: Official WhatsApp Business API
- **Approval Required**: Yes (business verification)
- **Session Duration**: Permanent (API keys valid until revoked)
- **Scalability**: Enterprise-grade (millions of messages)
- **Cost**: Per-message pricing

### **Session Lifecycle**

```
┌─────────────────────────────────────────────────────────────────┐
│                    WhatsApp account Lifecycle                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. Creation                                                     │
│     ├─ WebJS: QR Code generation                                 │
│     └─ Meta API: API key configuration                          │
│                                                                 │
│  2. Connection                                                   │
│     ├─ Session initialization                                      │
│     ├─ Health verification                                       │
│     └─ Registration in database                                  │
│                                                                 │
│  3. Active Usage                                                │
│     ├─ Message sending                                           │
│     ├─ Health monitoring                                        │
│     └─ Performance tracking                                     │
│                                                                 │
│  4. Maintenance                                                 │
│     ├─ Health checks                                            │
│     ├─ Automatic reconnection                                    │
│     └─ Load balancing                                           │
│                                                                 │
│  5. Disconnection                                               │
│     ├─ Session expiration                                        │
│     ├─ Manual disconnection                                    │
│     └─ Error handling                                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔧 Session Management Implementation

### **Session Model**

```php
<?php

namespace App\Models;

use App\Http\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppAccount extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'workspace_id',
        'session_id',
        'phone_number',
        'provider_type',
        'status',
        'qr_code',
        'session_data',
        'is_primary',
        'is_active',
        'health_score',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'session_data' => 'encrypted:array',
        'metadata' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'health_score' => 'integer',
        'last_activity_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['formatted_phone_number', 'health_status'];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'whatsapp_account_id');
    }

    public function campaignLogs(): HasMany
    {
        return $this->hasMany(CampaignLog::class, 'whatsapp_account_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByProvider($query, $provider)
    {
        return $query->where('provider_type', $provider);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    // Accessors
    public function getFormattedPhoneNumberAttribute(): string
    {
        if (!$this->phone_number) {
            return 'Not Set';
        }

        $phone = preg_replace('/[^\d+]/', '', $this->phone_number);

        if (strlen($phone) >= 10) {
            return '+' . ltrim($phone, '+');
        }

        return $phone;
    }

    public function getHealthStatusAttribute(): string
    {
        if ($this->health_score >= 90) {
            return 'excellent';
        } elseif ($this->health_score >= 70) {
            return 'good';
        } elseif ($this->health_score >= 50) {
            return 'fair';
        } elseif ($this->health_score >= 30) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    // Business Methods
    public function isHealthy(): bool
    {
        return $this->health_score >= 70 && $this->status === 'connected' && $this->is_active;
    }

    public function getCapacity(): int
    {
        $baseCapacity = 1000; // messages per hour

        if ($this->provider_type === 'meta') {
            $baseCapacity = 5000; // Meta API has higher capacity
        }

        $healthMultiplier = $this->health_score / 100;
        $activityMultiplier = $this->getActivityMultiplier();

        return (int) ($baseCapacity * $healthMultiplier * $activityMultiplier);
    }

    public function updateHealthScore(): void
    {
        $calculator = app(SessionHealthCalculator::class);
        $this->health_score = $calculator->calculateSessionHealth($this);
        $this->save();
    }

    public function updateLastActivity(): void
    {
        $this->last_activity_at = now();
        $this->save();
    }

    public function canHandleTemplate(): bool
    {
        // All sessions can handle templates with proper formatting
        return $this->isConnected();
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isExpired(): bool
    {
        if ($this->provider_type === 'meta') {
            return false; // Meta API sessions don't expire
        }

        // WebJS sessions expire after inactivity
        $expireMinutes = 60; // 1 hour
        return $this->last_activity_at &&
               $this->last_activity_at->lt(now()->subMinutes($expireMinutes));
    }

    private function getActivityMultiplier(): float
    {
        $oneHourAgo = now()->subHour();
        $recentMessages = $this->chats()
            ->where('type', 'outbound')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($recentMessages === 0) {
            return 0.5; // Low activity reduces capacity
        }

        return min(2.0, 1.0 + ($recentMessages / 1000)); // Cap at 2x capacity
    }
}
```

### **Session Service**

```php
<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\workspace;
use App\Models\User;
use App\Jobs\GenerateQRCodeJob;
use App\Jobs\ReconnectSessionJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppAccountService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function createWebJSSession(array $data)
    {
        try {
            DB::beginTransaction();

            $session = WhatsAppAccount::create([
                'workspace_id' => $this->workspaceId,
                'session_id' => 'webjs_' . uniqid(),
                'phone_number' => $data['phone_number'] ?? null,
                'provider_type' => 'webjs',
                'status' => 'connecting',
                'is_primary' => $this->shouldBePrimary(),
                'is_active' => true,
                'health_score' => 0, // Will be calculated after connection
                'created_by' => auth()->id(),
                'metadata' => [
                    'created_from' => 'web',
                    'browser_info' => $data['browser_info'] ?? [],
                    'ip_address' => request()->ip(),
                ]
            ]);

            // Generate QR code for WebJS connection
            GenerateQRCodeJob::dispatch($session->id)
                ->onQueue('whatsapp-accounts');

            DB::commit();

            Log::info('WebJS session created', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $session->id,
                'phone_number' => $data['phone_number'],
            ]);

            return (object) [
                'success' => true,
                'data' => $session,
                'message' => 'WhatsApp account created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create WebJS session', [
                'workspace_id' => $this->workspaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage()
            ];
        }
    }

    public function connectWebJSSession(string $sessionId, array $sessionData)
    {
        $session = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('uuid', $sessionId)
            ->firstOrFail();

        try {
            $session->update([
                'status' => 'connected',
                'session_data' => $sessionData,
                'last_connected_at' => now(),
                'qr_code' => null, // Clear QR code after connection
                'metadata' => array_merge($session->metadata ?? [], [
                    'connected_at' => now()->toISOString(),
                    'connection_info' => $sessionData,
                ])
            ]);

            // Set as primary if no primary exists
            $primaryExists = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('provider_type', 'webjs')
                ->where('is_primary', true)
                ->exists();

            if (!$primaryExists) {
                $session->update(['is_primary' => true]);
            }

            // Calculate initial health score
            $session->updateHealthScore();

            Log::info('WebJS session connected', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $session->id,
                'phone_number' => $session->phone_number,
            ]);

            return (object) [
                'success' => true,
                'data' => $session->fresh(),
                'message' => 'WhatsApp account connected successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to connect WebJS session', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to connect session: ' . $e->getMessage()
            ];
        }
    }

    public function disconnectSession(string $sessionId)
    {
        $session = WhatsAppAccount::where('workspace_id', $this->workspaceId)
            ->where('uuid', $sessionId)
            ->firstOrFail();

        try {
            $session->update([
                'status' => 'disconnected',
                'is_active' => false,
                'metadata' => array_merge($session->metadata ?? [], [
                    'disconnected_at' => now()->toISOString(),
                    'disconnected_by' => auth()->id(),
                ])
            ]);

            // If this was primary, promote another session
            if ($session->is_primary && $session->provider_type === 'webjs') {
                $this->promoteNewPrimarySession();
            }

            Log::info('WhatsApp account disconnected', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $session->id,
                'provider_type' => $session->provider_type,
            ]);

            return (object) [
                'success' => true,
                'message' => 'Session disconnected successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to disconnect session', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return (object) [
                'success' => false,
                'message' => 'Failed to disconnect session: ' . $e->getMessage()
            ];
        }
    }

    public function setPrimarySession(string $sessionId)
    {
        DB::transaction(function () use ($sessionId) {
            // Remove primary status from all sessions
            WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('provider_type', 'webjs')
                ->update(['is_primary' => false]);

            // Set new primary session
            $session = WhatsAppAccount::where('workspace_id', $this->workspaceId)
                ->where('uuid', $sessionId)
                ->firstOrFail();

            $session->update(['is_primary' => true]);

            Log::info('Primary session set', [
                'workspace_id' => $this->workspaceId,
                'session_id' => $session->id,
                'phone_number' => $session->phone_number,
            ]);
        });
    }

    protected function promoteNewPrimarySession(): void
    {
        $newPrimary = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('provider_type', 'webjs')
            ->where('status', 'connected')
            ->where('is_active', true)
            ->orderByDesc('health_score')
            ->first();

        if ($newPrimary) {
            $newPrimary->update(['is_primary' => true]);
        }
    }

    protected function shouldBePrimary(): bool
    {
        return !WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('provider_type', 'webjs')
            ->exists();
    }
}
```

### **Session Health Calculator**

```php
<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SessionHealthCalculator
{
    private const WEIGHTS = [
        'connection_stability' => 0.30,    // 30%
        'message_success_rate' => 0.25,   // 25%
        'recent_activity' => 0.20,         // 20%
        'error_rate' => -0.20,             // -20%
        'response_time' => 0.15,           // 15%
        'uptime' => 0.10                  // 10%
    ];

    public function calculateSessionHealth(WhatsAppAccount $session): int
    {
        $metrics = $this->collectMetrics($session);

        $healthScore = 100;

        // Apply weighted factors
        $healthScore += $metrics['connection_stability'] * self::WEIGHTS['connection_stability'];
        $healthScore += $metrics['message_success_rate'] * self::WEIGHTS['message_success_rate'];
        $healthScore += $metrics['recent_activity'] * self::WEIGHTS['recent_activity'];
        $healthScore += $metrics['error_rate'] * self::WEIGHTS['error_rate'];
        $healthScore += $metrics['response_time'] * self::WEIGHTS['response_time'];
        $healthScore += $metrics['uptime'] * self::WEIGHTS['uptime'];

        return max(0, min(100, round($healthScore)));
    }

    private function collectMetrics(WhatsAppAccount $session): array
    {
        return [
            'connection_stability' => $this->getConnectionStability($session),
            'message_success_rate' => $this->getMessageSuccessRate($session),
            'recent_activity' => $this->getRecentActivityScore($session),
            'error_rate' => $this->getErrorRate($session),
            'response_time' => $this->getResponseTimeScore($session),
            'uptime' => $this->getUptimeScore($session),
        ];
    }

    private function getConnectionStability(WhatsAppAccount $session): int
    {
        switch ($session->status) {
            case 'connected':
                return 100;
            case 'connecting':
                return 60;
            case 'disconnected':
                return 20;
            case 'error':
                return 0;
            default:
                return 50;
        }
    }

    private function getMessageSuccessRate(WhatsAppAccount $session): int
    {
        $oneHourAgo = now()->subHour();

        $totalMessages = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($totalMessages === 0) {
            return 80; // Default score for no recent activity
        }

        $successfulMessages = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        $successRate = ($successfulMessages / $totalMessages) * 100;

        // Bonus for high success rates
        if ($successRate >= 95) {
            return 100;
        }

        return min(100, max(0, $successRate));
    }

    private function getRecentActivityScore(WhatsAppAccount $session): int
    {
        $lastActivity = $session->last_activity_at;

        if (!$lastActivity) {
            return 0;
        }

        $minutesSinceActivity = $lastActivity->diffInMinutes(now());

        if ($minutesSinceActivity <= 5) {
            return 100; // Very recent activity
        } elseif ($minutesSinceActivity <= 15) {
            return 90; // Recent activity
        } elseif ($minutesSinceActivity <= 30) {
            return 70; // Moderately recent
        } elseif ($minutesSinceActivity <= 60) {
            return 50; // Somewhat recent
        } else {
            return max(0, 50 - ($minutesSinceActivity - 60)); // Decrease over time
        }
    }

    private function getErrorRate(WhatsAppAccount $session): int
    {
        $oneHourAgo = now()->subHour();

        $totalMessages = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($totalMessages === 0) {
            return 0; // No errors in empty period
        }

        $failedMessages = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->where('status', 'failed')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        return ($failedMessages / $totalMessages) * 100;
    }

    private function getResponseTimeScore(WhatsAppAccount $session): int
    {
        $oneHourAgo = now()->subHour();

        $avgResponseTime = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', $oneHourAgo)
            ->whereNotNull('metadata->response_time_ms')
            ->avg('metadata->response_time_ms');

        if (!$avgResponseTime) {
            return 90; // Good default if no data
        }

        // Convert to 0-100 scale (lower is better)
        $maxAcceptableTime = 5000; // 5 seconds
        $score = 100 - (($avgResponseTime / $maxAcceptableTime) * 100);

        return max(0, $score);
    }

    private function getUptimeScore(WhatsAppAccount $session): int
    {
        $created = $session->created_at;
        $now = now();
        $totalTime = $created->diffInSeconds($now);

        if ($totalTime === 0) {
            return 100;
        }

        if ($session->status === 'connected') {
            $upTime = $now->diffInSeconds($session->last_connected_at ?? $created);
        } else {
            // Calculate last connected period
            $lastConnectedAt = $session->last_connected_at ?? $created;
            $disconnectedAt = $session->last_activity_at ?? $now;
            $upTime = $disconnectedAt->diffInSeconds($lastConnectedAt);
        }

        $uptimePercentage = ($upTime / $totalTime) * 100;

        // Bonus for high uptime
        if ($uptimePercentage >= 99) {
            return 100;
        }

        return $uptimePercentage;
    }
}
```

### **Session Monitoring Service**

```php
<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\workspace;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SessionMonitoringService
{
    protected $workspaceId;

    public function __construct($workspaceId)
    {
        $this->workspaceId = $workspaceId;
    }

    public function monitorAllSessions(): array
    {
        $sessions = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->with(['workspace'])
            ->get();

        $results = [];

        foreach ($sessions as $session) {
            $results[] = $this->monitorSession($session);
        }

        return $results;
    }

    public function monitorSession(WhatsAppAccount $session): array
    {
        $now = now();

        // Calculate current health
        $healthScore = $session->health_score;
        $previousHealth = $session->getOriginal('health_score') ?? $healthScore;

        $monitoring = [
            'session_id' => $session->id,
            'uuid' => $session->uuid,
            'phone_number' => $session->phone_number,
            'provider_type' => $session->provider_type,
            'status' => $session->status,
            'is_primary' => $session->is_primary,
            'health_score' => $healthScore,
            'previous_health' => $previousHealth,
            'health_change' => $healthScore - $previousHealth,
            'is_healthy' => $session->isHealthy(),
            'last_activity_at' => $session->last_activity_at,
            'minutes_since_activity' => $session->last_activity_at
                ? $session->last_activity_at->diffInMinutes($now)
                : null,
            'issues' => $this->detectSessionIssues($session),
            'recommendations' => $this->generateRecommendations($session),
            'monitored_at' => $now->toISOString()
        ];

        // Check for critical issues
        if (!$session->isHealthy()) {
            $this->handleUnhealthySession($session);
        }

        return $monitoring;
    }

    public function detectSessionIssues(WhatsAppAccount $session): array
    {
        $issues = [];

        // Check for common issues
        if ($session->status !== 'connected') {
            $issues[] = [
                'type' => 'connection',
                'severity' => 'high',
                'message' => "Session is {$session->status}",
                'solution' => 'Reconnect the session or check WhatsApp connectivity'
            ];
        }

        if ($session->isExpired()) {
            $issues[] = [
                'type' => 'expiration',
                'severity' => 'high',
                'message' => 'Session has expired due to inactivity',
                'solution' => 'Generate new QR code and reconnect'
            ];
        }

        if ($session->health_score < 30) {
            $issues[] = [
                'type' => 'health',
                'severity' => 'high',
                'message' => 'Session health score is critically low',
                'solution' => 'Investigate error rates and performance issues'
            ];
        }

        if ($this->hasHighErrorRate($session)) {
            $issues[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => 'High error rate detected',
                'solution' => 'Check message content and network connectivity'
            ];
        }

        if ($this->hasSlowResponseTime($session)) {
            $issues[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => 'Slow response times detected',
                'solution' => 'Check network performance and provider status'
            ];
        }

        return $issues;
    }

    public function generateRecommendations(WhatsAppAccount $session): array
    {
        $recommendations = [];

        if ($session->health_score < 50) {
            $recommendations[] = [
                'type' => 'maintenance',
                'priority' => 'high',
                'action' => 'reconnect_session',
                'message' => 'Consider reconnecting the session to improve health'
            ];
        }

        if (!$session->is_primary && $this->hasBetterHealthCandidate()) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'medium',
                'action' => 'promote_to_primary',
                'message' => 'Session could be promoted to primary for better performance'
            ];
        }

        if ($this->shouldOptimizeLoadBalancing()) {
            $recommendations[] = [
                'type' => 'scaling',
                'priority' => 'medium',
                'action' => 'add_secondary_session',
                'message' => 'Add another session to improve load balancing'
            ];
        }

        return $recommendations;
    }

    protected function handleUnhealthySession(WhatsAppAccount $session): void
    {
        $severity = $this->determineIssueSeverity($session);

        Log::warning('Unhealthy WhatsApp account detected', [
            'workspace_id' => $this->workspaceId,
            'session_id' => $session->id,
            'phone_number' => $session->phone_number,
            'health_score' => $session->health_score,
            'issues' => $this->detectSessionIssues($session)
        ]);

        // Send alerts based on severity
        if ($severity === 'critical') {
            $this->sendCriticalAlert($session);
        } elseif ($severity === 'high') {
            $this->sendHighAlert($session);
        }

        // Attempt automatic recovery
        $this->attemptAutomaticRecovery($session);
    }

    protected function attemptAutomaticRecovery(WhatsAppAccount $session): void
    {
        try {
            // Reconnect WebJS sessions
            if ($session->provider_type === 'webjs' && $session->status === 'disconnected') {
                ReconnectSessionJob::dispatch($session->id)
                    ->onQueue('whatsapp-accounts')
                    ->delay(now()->addMinutes(1));

                Log::info('Automatic reconnection queued', [
                    'session_id' => $session->id,
                    'phone_number' => $session->phone_number
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to queue automatic reconnection', [
                'session_id' => $session->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function hasHighErrorRate(WhatsAppAccount $session): bool
    {
        $oneHourAgo = now()->subHour();
        $totalMessages = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        if ($totalMessages < 10) {
            return false; // Not enough data to determine
        }

        $failedMessages = DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->where('status', 'failed')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        $errorRate = ($failedMessages / $totalMessages) * 100;

        return $errorRate > 15; // 15% error rate threshold
    }

    protected function hasSlowResponseTime(WhatsAppAccount $session): bool
    {
        $avgResponseTime = $this->getAverageResponseTime($session);

        return $avgResponseTime > 5000; // 5 seconds threshold
    }

    protected function getAverageResponseTime(WhatsAppAccount $session): float
    {
        return DB::table('chats')
            ->where('whatsapp_account_id', $session->id)
            ->where('type', 'outbound')
            ->whereNotNull('metadata->response_time_ms')
            ->avg('metadata->response_time_ms') ?? 0;
    }

    protected function determineIssueSeverity(WhatsAppAccount $session): string
    {
        if ($session->status === 'error' || $session->health_score < 20) {
            return 'critical';
        }

        if ($session->health_score < 50 || $this->hasHighErrorRate($session)) {
            return 'high';
        }

        if ($session->health_score < 70 || $this->hasSlowResponseTime($session)) {
            return 'medium';
        }

        return 'low';
    }

    protected function sendCriticalAlert(WhatsAppAccount $session): void
    {
        // Implement critical alert notification
        // This could send email, Slack notification, etc.
    }

    protected function sendHighAlert(WhatsAppAccount $session): void
    {
        // Implement high priority alert notification
    }

    private function hasBetterHealthCandidate(): bool
    {
        return WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('provider_type', 'webjs')
            ->where('status', 'connected')
            ->where('health_score', '>', 70)
            ->where('is_primary', false)
            ->exists();
    }

    private function shouldOptimizeLoadBalancing(): bool
    {
        $activeSessions = WhatsAppAccount::forWorkspace($this->workspaceId)
            ->where('is_active', true)
            ->count();

        return $activeSessions === 1; // Only one active session
    }
}
```

---

## 🔧 Session Operations

### **Create New Session**

```php
// Controller method
public function createSession(Request $request)
{
    $service = new WhatsAppAccountService(session()->get('current_workspace'));

    $result = $service->createWebJSSession([
        'phone_number' => $request->phone_number,
        'browser_info' => [
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip()
        ]
    ]);

    return response()->json($result);
}
```

### **List Sessions**

```php
public function listSessions(Request $request)
{
    $sessions = WhatsAppAccount::forWorkspace($this->workspaceId)
        ->with(['workspace'])
        ->withCount(['chats' => function ($query) {
            $query->where('type', 'outbound')->where('created_at', '>=', now()->subDay());
        }])
        ->get()
        ->map(function ($session) {
            return [
                'id' => $session->uuid,
                'phone_number' => $session->formatted_phone_number,
                'provider_type' => $session->provider_type,
                'status' => $session->status,
                'is_primary' => $session->is_primary,
                'health_score' => $session->health_score,
                'health_status' => $session->health_status,
                'capacity' => $session->getCapacity(),
                'last_activity' => $session->last_activity_at,
                'messages_today' => $session->chats_count,
                'created_at' => $session->created_at
            ];
        });

    return response()->json($sessions);
}
```

### **Session Health Check**

```php
public function checkSessionHealth(string $sessionId)
{
    $session = WhatsAppAccount::where('uuid', $sessionId)
        ->where('workspace_id', $this->workspaceId)
        ->firstOrFail();

    $monitoringService = new SessionMonitoringService($this->workspaceId);
    $healthData = $monitoringService->monitorSession($session);

    return response()->json($healthData);
}
```

### **Session Actions**

```php
public function performSessionAction(Request $request, string $sessionId)
{
    $session = WhatsAppAccount::where('uuid', $sessionId)
        ->where('workspace_id', $this->workspaceId)
        ->firstOrFail();

    $action = $request->action;
    $service = new WhatsAppAccountService($this->workspaceId);

    switch ($action) {
        case 'reconnect':
            $result = $service->reconnectSession($session->id);
            break;

        case 'disconnect':
            $result = $service->disconnectSession($session->uuid);
            break;

        case 'set_primary':
            $result = $service->setPrimarySession($session->uuid);
            break;

        case 'regenerate_qr':
            $result = $service->regenerateQRCode($session->id);
            break;

        default:
            return response()->json([
                'success' => false,
                'message' => 'Invalid action'
            ], 400);
    }

    return response()->json($result);
}
```

---

## 📱 QR Code Generation

### **QR Code Job**

```php
<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\QrCode;

class GenerateQRCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    public function __construct(private int $sessionId)
    {
        $this->onQueue('whatsapp-accounts');
    }

    public function handle()
    {
        $session = WhatsAppAccount::findOrFail($this->sessionId);

        try {
            // Generate QR code for WebJS connection
            $qrCodeData = $this->generateQRCode($session);

            // Store QR code
            $session->update([
                'qr_code' => $qrCodeData,
                'metadata' => array_merge($session->metadata ?? [], [
                    'qr_generated_at' => now()->toISOString()
                ])
            ]);

            Log::info('QR code generated for WebJS session', [
                'session_id' => $this->sessionId,
                'workspace_id' => $session->workspace_id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate QR code', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark session as failed
            $session->update(['status' => 'error']);

            throw $e;
        }
    }

    protected function generateQRCode(WhatsAppAccount $session): string
    {
        // Generate session connection URL
        $connectionUrl = route('whatsapp.web.connect', ['session_id' => $session->uuid]);

        // Generate QR code
        $qrCode = QrCode::generate($connectionUrl);

        // Store QR code image
        $fileName = "qr_code_{$session->id}_" . time() . '.png';
        $filePath = "whatsapp/qr-codes/{$fileName}";

        Storage::disk('public')->put($filePath, $qrCode);

        return Storage::url($filePath);
    }

    public function failed(\Throwable $exception)
    {
        $session = WhatsAppAccount::find($this->sessionId);
        if ($session) {
            $session->update(['status' => 'error']);
        }

        Log::error('QR Code generation failed permanently', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
```

### **Frontend QR Code Display**

```vue
<!-- resources/js/Components/WhatsAppQRCode.vue -->
<template>
  <div class="qr-code-container">
    <div class="qr-code-header">
      <h3>Scan QR Code</h3>
      <p class="text-sm text-gray-600">
        Scan this QR code with WhatsApp to connect your session
      </p>
    </div>

    <div class="qr-code-content">
      <div v-if="loading" class="flex justify-center items-center h-64">
        <div class="animate-spin">
          <div class="border-4 border-blue-500 border-t-transparent border-r-transparent rounded-full w-12 h-12"></div>
        </div>
      </div>

      <div v-else-if="qrCode" class="qr-code-image">
        <img :src="qrCode" alt="WhatsApp QR Code" />

        <div v-if="expiresAt" class="mt-4 text-center">
          <p class="text-sm text-orange-600">
            <i class="fas fa-clock"></i>
            Expires: {{ formatTime(expiresAt) }}
          </p>
        </div>
      </div>

      <div v-else class="qr-code-error">
        <div class="text-center text-red-600">
          <i class="fas fa-exclamation-triangle"></i>
          <p>Failed to generate QR code</p>
        </div>
      </div>
    </div>

    <div class="qr-code-footer">
      <div v-if="status === 'connecting'" class="flex items-center justify-between">
        <span class="text-sm text-blue-600">
          <i class="fas fa-spinner fa-spin"></i>
          Connecting...
        </span>
        <button
          @click="cancelConnection"
          class="text-sm text-red-600 hover:text-red-800"
        >
          Cancel
        </button>
      </div>

      <div v-else-if="status === 'connected'" class="text-center">
        <span class="text-green-600">
          <i class="fas fa-check-circle"></i>
          Connected successfully!
        </span>
      </div>

      <div v-else class="flex space-x-4">
        <button
          @click="regenerateQRCode"
          :disabled="loading"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          <i class="fas fa-refresh"></i>
          Regenerate
        </button>

        <button
          @click="copySessionId"
          class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700"
        >
          <i class="fas fa-copy"></i>
          Copy ID
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Echo } from 'laravel-echo'

const props = defineProps(['sessionId'])
const emit = defineEmits(['connected', 'disconnected', 'error'])

const loading = ref(false)
const qrCode = ref('')
const status = ref('waiting')
const expiresAt = ref(null)
const pollingInterval = ref(null)

const loadQRCode = async () => {
  loading.value = true
  status.value = 'loading'

  try {
    const response = await fetch(`/api/whatsapp/sessions/${props.sessionId}/qr-code`)
    const data = await response.json()

    if (data.success) {
      qrCode.value = data.qr_code
      expiresAt.value = data.expires_at
      status.value = 'ready'

      // Start polling for connection status
      startPolling()
    } else {
      status.value = 'error'
      emit('error', data.message)
    }
  } catch (error) {
    status.value = 'error'
    emit('error', 'Failed to load QR code')
  } finally {
    loading.value = false
  }
}

const startPolling = () => {
  pollingInterval.value = setInterval(() => {
    checkConnectionStatus()
  }, 3000) // Check every 3 seconds
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(polling.value)
    pollingInterval.value = null
  }
}

const checkConnectionStatus = async () => {
  try {
    const response = await fetch(`/api/whatsapp/sessions/${props.sessionId}/status`)
    const data = await response.json()

    if (data.success) {
      status.value = data.status

      if (data.status === 'connected') {
        stopPolling()
        emit('connected', data.session)
      } else if (data.status === 'error') {
        stopPolling()
        emit('error', data.message)
      }
    }
  } catch (error) {
    console.error('Failed to check connection status:', error)
  }
}

const regenerateQRCode = async () => {
  await loadQRCode()
}

const copySessionId = () => {
  navigator.clipboard.writeText(props.sessionId)

  // Show success feedback
  const button = document.querySelector('[data-testid="copy-button"]')
  const originalText = button?.textContent
  button.textContent = 'Copied!'

  setTimeout(() => {
    button.textContent = originalText
  }, 2000)
}

const cancelConnection = async () => {
  try {
    await fetch(`/api/whatsapp/sessions/${props.sessionId}/cancel`, {
      method: 'POST'
    })

    status.value = 'cancelled'
    emit('disconnected')
  } catch (error) {
    console.error('Failed to cancel connection:', error)
  }
}

onMounted(() => {
  loadQRCode()
})

onUnmounted(() => {
  stopPolling()
})
</script>
```

---

## 🚀 Best Practices

### **Session Management**

1. **Always use workspace-scoped queries**
2. **Implement proper error handling and logging**
3. **Use database transactions for state changes**
4. **Implement automatic health monitoring**
5. **Set up proper alerting for unhealthy sessions**

### **Performance Optimization**

1. **Use database indexes for frequent queries**
2. **Implement caching for session health scores**
3. **Use queues for background operations**
4. **Monitor resource usage and implement limits**
5. **Implement proper cleanup for expired sessions**

### **Security Considerations**

1. **Validate all session data**
2. **Encrypt sensitive session information**
3. **Implement proper authorization checks**
4. **Use secure QR code generation**
5. **Audit session creation and modification**

### **Monitoring & Alerting**

1. **Set up real-time health monitoring**
2. **Implement threshold-based alerting**
3. **Track session performance metrics**
4. **Monitor error rates and response times**
5. **Set up automated recovery mechanisms**

---

This comprehensive guide provides a complete reference for WhatsApp account management, covering creation, monitoring, maintenance, and optimization of both WebJS and Meta API sessions.