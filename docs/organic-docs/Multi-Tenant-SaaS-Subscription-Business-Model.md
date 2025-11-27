# 💼 Multi-Tenant SaaS Subscription Business Model - Complete Implementation Guide

## 📋 Business Model Overview

**Blazz Platform** mengimplementasikan **Multi-Tenant Software-as-a-Service (SaaS)** model dengan subscription-based revenue generation. Platform ini dirancang untuk mensupport berbagai tingkatan business dari SME hingga enterprise dengan feature-tiered pricing dan workspace-based multi-tenancy.

### 🎯 Business Model Components

1. **Multi-Tenant Architecture** - Single application instance serving multiple customers
2. **Subscription Tiers** - Feature-limited pricing plans
3. **Usage-Based Limits** - Dynamic resource allocation
4. **Automated Billing** - Recurring payment processing
5. **Workspace Isolation** - Complete data and feature separation

## 🏗️ Multi-Tenant Architecture Implementation

### Workspace-Based Tenancy

**Core Workspace Model**
```php
// app/Models/Workspace.php
class Workspace extends Model {
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'metadata' => 'array',
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function users() {
        return $this->belongsToMany(User::class, 'teams')
                    ->withPivot('role', 'permissions')
                    ->withTimestamps();
    }

    public function teams() {
        return $this->hasMany(Team::class);
    }

    public function subscription() {
        return $this->hasOne(Subscription::class)->latest();
    }

    public function contacts() {
        return $this->hasMany(Contact::class);
    }

    public function chats() {
        return $this->hasMany(Chat::class);
    }

    public function campaigns() {
        return $this->hasMany(Campaign::class);
    }

    public function getActiveSubscriptionAttribute() {
        return $this->subscription()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->first();
    }

    public function getCurrentPlanAttribute() {
        return $this->activeSubscription?->plan;
    }

    public function getUsageMetricsAttribute() {
        return [
            'contacts_count' => $this->contacts()->whereNull('deleted_at')->count(),
            'messages_sent_this_month' => $this->chats()
                ->where('type', 'outbound')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'campaigns_this_month' => $this->campaigns()
                ->whereMonth('created_at', now()->month)
                ->count(),
            'active_agents' => $this->teams()
                ->where('role', 'agent')
                ->whereNull('deleted_at')
                ->count(),
        ];
    }
}
```

**Team-Based User Management**
```php
// app/Models/Team.php
class Team extends Model {
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'permissions' => 'array',
        'last_active_at' => 'datetime',
    ];

    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function role() {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    public function tickets() {
        return $this->hasMany(ChatTicket::class, 'assigned_to', 'user_id');
    }

    public function ticketsCount() {
        return $this->tickets()->where('status', 'open')->count();
    }

    // Workspace-scoped queries
    public function scopeForWorkspace($query, $workspaceId) {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByRole($query, $role) {
        return $query->where('role', $role);
    }
}
```

### Data Isolation Strategy

**Global Scopes for Workspace Isolation**
```php
// app/Models/Traits/HasWorkspace.php
trait HasWorkspace {
    protected static function bootHasWorkspace() {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (auth()->check() && request()->route('workspace')) {
                $workspaceId = request()->route('workspace')->id;
                $builder->where('workspace_id', $workspaceId);
            }
        });

        static::creating(function ($model) {
            if (auth()->check() && request()->route('workspace')) {
                $model->workspace_id = request()->route('workspace')->id;
            }
        });
    }

    public function scopeForWorkspace($query, $workspaceId) {
        return $query->where('workspace_id', $workspaceId);
    }
}
```

**Route Model Binding for Workspace Context**
```php
// routes/web.php
Route::middleware(['auth', 'workspace'])->group(function () {
    Route::prefix('{workspace}')->group(function () {
        Route::resource('contacts', ContactController::class);
        Route::resource('campaigns', CampaignController::class);
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    });
});

// app/Http/Middleware/WorkspaceMiddleware.php
class WorkspaceMiddleware {
    public function handle($request, Closure $next) {
        $workspace = $request->route('workspace');

        if (!$workspace) {
            return redirect()->route('dashboard');
        }

        // Verify user has access to this workspace
        $userTeam = Team::where('user_id', auth()->id())
                       ->where('workspace_id', $workspace->id)
                       ->first();

        if (!$userTeam) {
            abort(403, 'Access denied to this workspace');
        }

        // Share workspace context with all views
        view()->share('currentWorkspace', $workspace);
        view()->share('currentUserRole', $userTeam->role);

        return $next($request);
    }
}
```

## 💰 Subscription Management System

### Subscription Plan Configuration

**Subscription Plans Model**
```php
// app/Models/SubscriptionPlan.php
class SubscriptionPlan extends Model {
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'features' => 'array',
        'pricing' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions() {
        return $this->hasMany(Subscription::class);
    }

    public function getFormattedPriceAttribute() {
        return '$' . number_format($this->pricing['monthly'] ?? 0, 2);
    }

    public function getFeatureListAttribute() {
        return collect($this->features)->map(function ($feature, $key) {
            return [
                'name' => $feature['name'] ?? $key,
                'value' => $feature['value'] ?? true,
                'unit' => $feature['unit'] ?? null,
                'description' => $feature['description'] ?? null,
            ];
        });
    }
}
```

**Predefined Plan Configuration**
```php
// database/seeders/SubscriptionPlanSeeder.php
class SubscriptionPlanSeeder extends Seeder {
    public function run() {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small businesses getting started',
                'pricing' => [
                    'monthly' => 29,
                    'yearly' => 290,
                    'currency' => 'USD'
                ],
                'features' => [
                    'contacts_limit' => ['value' => 100, 'unit' => 'contacts'],
                    'message_limit' => ['value' => 1000, 'unit' => 'messages/month'],
                    'campaigns_limit' => ['value' => 5, 'unit' => 'campaigns/month'],
                    'agents_limit' => ['value' => 2, 'unit' => 'agents'],
                    'whatsapp_accounts' => ['value' => 1, 'unit' => 'session'],
                    'api_access' => ['value' => false],
                    'analytics' => ['value' => 'basic'],
                    'support' => ['value' => 'email'],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Ideal for growing businesses',
                'pricing' => [
                    'monthly' => 79,
                    'yearly' => 790,
                    'currency' => 'USD'
                ],
                'features' => [
                    'contacts_limit' => ['value' => 1000, 'unit' => 'contacts'],
                    'message_limit' => ['value' => 10000, 'unit' => 'messages/month'],
                    'campaigns_limit' => ['value' => 25, 'unit' => 'campaigns/month'],
                    'agents_limit' => ['value' => 5, 'unit' => 'agents'],
                    'whatsapp_accounts' => ['value' => 2, 'unit' => 'sessions'],
                    'api_access' => ['value' => true],
                    'analytics' => ['value' => 'advanced'],
                    'support' => ['value' => 'priority'],
                    'custom_templates' => ['value' => true],
                    'automation_rules' => ['value' => 10, 'unit' => 'rules'],
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Complete solution for large organizations',
                'pricing' => [
                    'monthly' => 199,
                    'yearly' => 1990,
                    'currency' => 'USD'
                ],
                'features' => [
                    'contacts_limit' => ['value' => 10000, 'unit' => 'contacts'],
                    'message_limit' => ['value' => 100000, 'unit' => 'messages/month'],
                    'campaigns_limit' => ['value' => 'unlimited'],
                    'agents_limit' => ['value' => 20, 'unit' => 'agents'],
                    'whatsapp_accounts' => ['value' => 5, 'unit' => 'sessions'],
                    'api_access' => ['value' => true],
                    'analytics' => ['value' => 'enterprise'],
                    'support' => ['value' => '24/7'],
                    'custom_templates' => ['value' => true],
                    'automation_rules' => ['value' => 'unlimited'],
                    'dedicated_account_manager' => ['value' => true],
                    'custom_integrations' => ['value' => true],
                    'sla_guarantee' => ['value' => '99.9%'],
                ],
                'is_active' => true,
                'sort_order' => 3,
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
```

### Subscription Lifecycle Management

**Subscription Model**
```php
// app/Models/Subscription.php
class Subscription extends Model {
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'auto_renew' => 'boolean',
    ];

    protected $dates = ['starts_at', 'expires_at', 'trial_ends_at', 'cancelled_at'];

    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function plan() {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function invoices() {
        return $this->hasMany(BillingInvoice::class);
    }

    public function transactions() {
        return $this->hasMany(BillingTransaction::class);
    }

    public function isActive() {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isOnTrial() {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isExpired() {
        return $this->expires_at->isPast();
    }

    public function isCancelled() {
        return $this->cancelled_at !== null;
    }

    public function getDaysUntilExpiryAttribute() {
        return $this->expires_at->diffInDays(now());
    }

    public function getUsagePercentageAttribute() {
        $limits = $this->plan->features;
        $usage = $this->workspace->usage_metrics;
        $percentages = [];

        foreach ($limits as $feature => $limit) {
            if (is_numeric($limit['value'])) {
                $usageKey = $this->getUsageKeyForFeature($feature);
                $currentUsage = $usage[$usageKey] ?? 0;
                $percentages[$feature] = ($currentUsage / $limit['value']) * 100;
            }
        }

        return $percentages;
    }

    private function getUsageKeyForFeature($feature) {
        $mapping = [
            'contacts_limit' => 'contacts_count',
            'message_limit' => 'messages_sent_this_month',
            'campaigns_limit' => 'campaigns_this_month',
            'agents_limit' => 'active_agents',
        ];

        return $mapping[$feature] ?? $feature;
    }
}
```

**Subscription Service**
```php
// app/Services/SubscriptionService.php
class SubscriptionService {
    public static function createSubscription(Workspace $workspace, SubscriptionPlan $plan, $paymentMethodId = null) {
        return DB::transaction(function () use ($workspace, $plan, $paymentMethodId) {
            // Cancel existing subscription if any
            self::cancelExistingSubscription($workspace);

            // Calculate subscription dates
            $startDate = now();
            $endDate = $startDate->copy()->addMonth();
            $trialEndDate = $workspace->subscriptions()->count() === 0
                ? $startDate->copy()->addDays(14)
                : null;

            // Create subscription
            $subscription = Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startDate,
                'expires_at' => $endDate,
                'trial_ends_at' => $trialEndDate,
                'auto_renew' => true,
                'billing_cycle' => 'monthly',
                'amount' => $plan->pricing['monthly'],
                'currency' => $plan->pricing['currency'],
            ]);

            // Process initial payment
            if ($paymentMethodId) {
                self::processPayment($subscription, $paymentMethodId);
            }

            // Send confirmation
            self::sendSubscriptionConfirmation($subscription);

            return $subscription;
        });
    }

    public static function isSubscriptionFeatureLimitReached($workspaceId, $feature) {
        $workspace = Workspace::find($workspaceId);
        $subscription = $workspace->activeSubscription;

        if (!$subscription) {
            return true; // No active subscription
        }

        $planFeatures = $subscription->plan->features;
        $limit = $planFeatures[$feature]['value'] ?? null;

        if ($limit === 'unlimited' || $limit === null) {
            return false;
        }

        $currentUsage = self::getCurrentUsage($workspaceId, $feature);

        return $currentUsage >= $limit;
    }

    public static function getCurrentUsage($workspaceId, $feature) {
        $workspace = Workspace::find($workspaceId);
        $usage = $workspace->usage_metrics;

        $featureMapping = [
            'contacts_limit' => 'contacts_count',
            'message_limit' => 'messages_sent_this_month',
            'campaigns_limit' => 'campaigns_this_month',
            'agents_limit' => 'active_agents',
            'whatsapp_accounts' => 'whatsapp_accounts_count',
        ];

        $usageKey = $featureMapping[$feature] ?? $feature;

        return $usage[$usageKey] ?? 0;
    }

    public static function checkAndEnforceLimits($workspaceId) {
        $workspace = Workspace::find($workspaceId);
        $subscription = $workspace->activeSubscription;

        if (!$subscription) {
            return ['status' => 'no_subscription'];
        }

        $warnings = [];
        $blocked = [];

        foreach ($subscription->plan->features as $feature => $limit) {
            if (is_numeric($limit['value'])) {
                $currentUsage = self::getCurrentUsage($workspaceId, $feature);
                $percentage = ($currentUsage / $limit['value']) * 100;

                if ($percentage >= 100) {
                    $blocked[] = [
                        'feature' => $feature,
                        'limit' => $limit['value'],
                        'current' => $currentUsage,
                        'message' => "You've reached your {$limit['name']} limit"
                    ];
                } elseif ($percentage >= 80) {
                    $warnings[] = [
                        'feature' => $feature,
                        'limit' => $limit['value'],
                        'current' => $currentUsage,
                        'percentage' => round($percentage),
                        'message' => "You've used {$percentage}% of your {$limit['name']} limit"
                    ];
                }
            }
        }

        return [
            'status' => 'checked',
            'warnings' => $warnings,
            'blocked' => $blocked,
            'subscription' => $subscription
        ];
    }

    private static function processPayment(Subscription $subscription, $paymentMethodId) {
        // Implementation varies by payment provider
        // This is a placeholder for Stripe/PayPal integration

        $paymentIntent = [
            'amount' => $subscription->amount * 100, // Convert to cents
            'currency' => strtolower($subscription->currency),
            'payment_method' => $paymentMethodId,
            'confirmation_method' => 'manual',
            'confirm' => true,
        ];

        // Process with Stripe/PayPal
        // Store transaction record

        BillingTransaction::create([
            'subscription_id' => $subscription->id,
            'workspace_id' => $subscription->workspace_id,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => 'completed',
            'type' => 'subscription_payment',
            'metadata' => json_encode([
                'billing_cycle' => $subscription->billing_cycle,
                'payment_method_id' => $paymentMethodId
            ])
        ]);
    }
}
```

## 💳 Payment Processing Integration

### Multi-Provider Payment Gateway

**Payment Gateway Adapter Pattern**
```php
// app/Contracts/PaymentGatewayInterface.php
interface PaymentGatewayInterface {
    public function createCustomer($email, $name);
    public function createPaymentIntent($amount, $currency, $customerId, $paymentMethodId);
    public function confirmPayment($paymentIntentId);
    public function createSubscription($customerId, $priceId, $paymentMethodId);
    public function cancelSubscription($subscriptionId);
    public function refundPayment($paymentIntentId, $amount = null);
    public function getCustomer($customerId);
}

// app/Services/PaymentGateways/StripePaymentGateway.php
class StripePaymentGateway implements PaymentGatewayInterface {
    private $stripe;

    public function __construct() {
        $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
    }

    public function createCustomer($email, $name) {
        return $this->stripe->customers->create([
            'email' => $email,
            'name' => $name,
        ]);
    }

    public function createPaymentIntent($amount, $currency, $customerId, $paymentMethodId) {
        return $this->stripe->paymentIntents->create([
            'amount' => $amount * 100, // Convert to cents
            'currency' => $currency,
            'customer' => $customerId,
            'payment_method' => $paymentMethodId,
            'confirmation_method' => 'manual',
            'confirm' => true,
        ]);
    }

    public function createSubscription($customerId, $priceId, $paymentMethodId) {
        return $this->stripe->subscriptions->create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'default_payment_method' => $paymentMethodId,
        ]);
    }

    public function cancelSubscription($subscriptionId) {
        return $this->stripe->subscriptions->cancel($subscriptionId);
    }
}
```

**Billing Service**
```php
// app/Services/BillingService.php
class BillingService {
    private PaymentGatewayInterface $paymentGateway;

    public function __construct(PaymentGatewayInterface $paymentGateway) {
        $this->paymentGateway = $paymentGateway;
    }

    public function processSubscriptionPayment(Subscription $subscription, $paymentMethodId) {
        try {
            $workspace = $subscription->workspace;

            // Create or retrieve customer
            if (!$workspace->stripe_customer_id) {
                $customer = $this->paymentGateway->createCustomer(
                    $workspace->owner->email,
                    $workspace->owner->full_name
                );
                $workspace->update(['stripe_customer_id' => $customer->id]);
            }

            // Process payment
            $paymentIntent = $this->paymentGateway->createPaymentIntent(
                $subscription->amount,
                strtolower($subscription->currency),
                $workspace->stripe_customer_id,
                $paymentMethodId
            );

            // Record transaction
            BillingTransaction::create([
                'subscription_id' => $subscription->id,
                'workspace_id' => $workspace->id,
                'transaction_id' => $paymentIntent->id,
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'status' => 'pending',
                'type' => 'subscription_payment',
                'gateway' => 'stripe',
                'metadata' => json_encode([
                    'payment_intent_id' => $paymentIntent->id,
                    'billing_cycle' => $subscription->billing_cycle
                ])
            ]);

            return $paymentIntent;

        } catch (Exception $e) {
            Log::error('Payment processing failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function generateInvoice(Subscription $subscription) {
        $invoice = BillingInvoice::create([
            'workspace_id' => $subscription->workspace_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'billing_period_start' => $subscription->starts_at,
            'billing_period_end' => $subscription->expires_at,
            'status' => 'pending',
            'due_date' => $subscription->expires_at->copy()->addDays(7),
        ]);

        // Add line items
        $invoice->items()->create([
            'description' => "{$subscription->plan->name} Plan - {$subscription->billing_cycle}",
            'quantity' => 1,
            'unit_price' => $subscription->amount,
            'total' => $subscription->amount,
        ]);

        return $invoice;
    }

    private function generateInvoiceNumber() {
        $prefix = 'BLZ';
        $year = date('Y');
        $sequence = BillingInvoice::whereYear('created_at', $year)->count() + 1;

        return sprintf("%s-%d-%06d", $prefix, $year, $sequence);
    }
}
```

## 📊 Usage Analytics & Monitoring

### Real-time Usage Tracking

**Usage Monitoring Service**
```php
// app/Services/UsageTrackingService.php
class UsageTrackingService {
    public static function trackMessageSent($workspaceId) {
        $cacheKey = "usage:messages:{$workspaceId}:" . now()->format('Y-m');

        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, now()->endOfMonth());

        // Check if limit reached
        if (SubscriptionService::isSubscriptionFeatureLimitReached($workspaceId, 'message_limit')) {
            event(new SubscriptionLimitReachedEvent($workspaceId, 'message_limit'));
        }
    }

    public static function trackContactCreated($workspaceId) {
        $cacheKey = "usage:contacts:{$workspaceId}";

        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, now()->endOfDay());

        // Update database
        Cache::remember("workspace_stats:{$workspaceId}", 3600, function () use ($workspaceId) {
            return [
                'contacts_count' => Contact::where('workspace_id', $workspaceId)->count(),
                'messages_this_month' => Chat::where('workspace_id', $workspaceId)
                    ->where('type', 'outbound')
                    ->whereMonth('created_at', now()->month)
                    ->count(),
            ];
        });
    }

    public static function getUsageMetrics($workspaceId) {
        return Cache::remember("usage_metrics:{$workspaceId}", 300, function () use ($workspaceId) {
            $workspace = Workspace::find($workspaceId);

            return [
                'contacts' => [
                    'current' => $workspace->contacts()->whereNull('deleted_at')->count(),
                    'limit' => $workspace->currentPlan?->features['contacts_limit']['value'] ?? null,
                    'percentage' => $this->calculatePercentage($workspace, 'contacts_limit')
                ],
                'messages' => [
                    'current' => $workspace->chats()
                        ->where('type', 'outbound')
                        ->whereMonth('created_at', now()->month)
                        ->count(),
                    'limit' => $workspace->currentPlan?->features['message_limit']['value'] ?? null,
                    'percentage' => $this->calculatePercentage($workspace, 'message_limit')
                ],
                'campaigns' => [
                    'current' => $workspace->campaigns()
                        ->whereMonth('created_at', now()->month)
                        ->count(),
                    'limit' => $workspace->currentPlan?->features['campaigns_limit']['value'] ?? null,
                    'percentage' => $this->calculatePercentage($workspace, 'campaigns_limit')
                ],
                'agents' => [
                    'current' => $workspace->teams()
                        ->where('role', 'agent')
                        ->whereNull('deleted_at')
                        ->count(),
                    'limit' => $workspace->currentPlan?->features['agents_limit']['value'] ?? null,
                    'percentage' => $this->calculatePercentage($workspace, 'agents_limit')
                ],
            ];
        });
    }

    private static function calculatePercentage($workspace, $feature) {
        $limit = $workspace->currentPlan?->features[$feature]['value'] ?? null;

        if ($limit === 'unlimited' || $limit === null) {
            return 0;
        }

        $usageMappings = [
            'contacts_limit' => fn($w) => $w->contacts()->whereNull('deleted_at')->count(),
            'message_limit' => fn($w) => $w->chats()
                ->where('type', 'outbound')
                ->whereMonth('created_at', now()->month)
                ->count(),
            'campaigns_limit' => fn($w) => $w->campaigns()
                ->whereMonth('created_at', now()->month)
                ->count(),
            'agents_limit' => fn($w) => $w->teams()
                ->where('role', 'agent')
                ->whereNull('deleted_at')
                ->count(),
        ];

        if (isset($usageMappings[$feature])) {
            $current = $usageMappings[$feature]($workspace);
            return ($current / $limit) * 100;
        }

        return 0;
    }
}
```

### Frontend Usage Dashboard Component

**Vue.js Usage Monitoring Component**
```vue
<!-- resources/js/Components/UsageDashboard.vue -->
<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-semibold mb-4">Usage Overview</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <UsageMetricCard
        v-for="metric in metrics"
        :key="metric.key"
        :metric="metric"
        @upgrade-clicked="handleUpgrade"
      />
    </div>

    <!-- Usage Warnings -->
    <div v-if="warnings.length > 0" class="mt-6 space-y-2">
      <div
        v-for="warning in warnings"
        :key="warning.feature"
        class="bg-yellow-50 border border-yellow-200 rounded-lg p-4"
      >
        <div class="flex items-center">
          <ExclamationTriangleIcon class="w-5 h-5 text-yellow-400 mr-2" />
          <span class="text-sm text-yellow-800">{{ warning.message }}</span>
        </div>
      </div>
    </div>

    <!-- Upgrade CTA -->
    <div v-if="showUpgradeCTA" class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-center justify-between">
        <div>
          <h4 class="text-sm font-medium text-blue-800">Upgrade Your Plan</h4>
          <p class="text-sm text-blue-600 mt-1">Get more features and higher limits</p>
        </div>
        <button
          @click="handleUpgrade"
          class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
        >
          Upgrade Now
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import UsageMetricCard from './UsageMetricCard.vue';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  workspaceId: String,
  currentPlan: Object,
});

const metrics = ref([]);
const warnings = ref([]);

const showUpgradeCTA = computed(() => {
  return metrics.value.some(metric => metric.percentage >= 80);
});

const loadUsageMetrics = async () => {
  try {
    const response = await axios.get(route('api.usage.metrics', {
      workspace: props.workspaceId
    }));

    metrics.value = response.data.metrics;
    warnings.value = response.data.warnings;
  } catch (error) {
    console.error('Failed to load usage metrics:', error);
  }
};

const handleUpgrade = () => {
  Inertia.visit(route('billing.plans', {
    workspace: props.workspaceId
  }));
};

onMounted(() => {
  loadUsageMetrics();

  // Refresh metrics every 5 minutes
  setInterval(loadUsageMetrics, 300000);
});
</script>
```

## 🔔 Automated Subscription Management

### Subscription Renewal & Expiration

**Automated Subscription Management**
```php
// app/Console/Commands/ManageSubscriptions.php
class ManageSubscriptions extends Command {
    protected $signature = 'subscriptions:manage';
    protected $description = 'Manage subscription renewals and expirations';

    public function handle() {
        $this->handleExpiringSubscriptions();
        $this->handleExpiredSubscriptions();
        $this->handleRenewals();
        $this->sendUsageWarnings();
    }

    private function handleExpiringSubscriptions() {
        $expiringSoon = Subscription::where('expires_at', '<=', now()->addDays(7))
                                   ->where('expires_at', '>', now())
                                   ->where('status', 'active')
                                   ->where('auto_renew', true)
                                   ->get();

        foreach ($expiringSoon as $subscription) {
            $this->info("Processing renewal for subscription {$subscription->id}");

            try {
                $this->processRenewal($subscription);
            } catch (Exception $e) {
                $this->error("Failed to renew subscription {$subscription->id}: {$e->getMessage()}");
                $this->notifyRenewalFailure($subscription);
            }
        }
    }

    private function handleExpiredSubscriptions() {
        $expired = Subscription::where('expires_at', '<', now())
                               ->where('status', 'active')
                               ->get();

        foreach ($expired as $subscription) {
            $this->info("Deactivating expired subscription {$subscription->id}");

            $subscription->update(['status' => 'expired']);

            // Notify workspace owner
            $this->notifySubscriptionExpired($subscription);

            // Limit workspace functionality
            $this->enforceExpiredLimitations($subscription->workspace);
        }
    }

    private function processRenewal(Subscription $subscription) {
        $workspace = $subscription->workspace;
        $plan = $subscription->plan;

        // Create new subscription period
        $newSubscription = Subscription::create([
            'workspace_id' => $workspace->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $subscription->expires_at,
            'expires_at' => $subscription->expires_at->copy()->addMonth(),
            'auto_renew' => true,
            'billing_cycle' => $subscription->billing_cycle,
            'amount' => $plan->pricing[$subscription->billing_cycle],
            'currency' => $plan->pricing['currency'],
        ]);

        // Process payment
        $billingService = new BillingService(app(PaymentGatewayInterface::class));
        $billingService->processSubscriptionPayment($newSubscription, $workspace->default_payment_method);

        // Update old subscription
        $subscription->update(['auto_renew' => false]);

        // Send confirmation
        $this->sendRenewalConfirmation($newSubscription);
    }
}
```

**Scheduled Task Setup**
```php
// app/Console/Kernel.php
class Kernel extends ConsoleKernel {
    protected function schedule(Schedule $schedule) {
        // Subscription management tasks
        $schedule->command('subscriptions:manage')->dailyAt('00:00');
        $schedule->command('usage:track')->hourly();
        $schedule->command('billing:generate-invoices')->monthlyOn(1, '01:00');

        // Cache cleanup
        $schedule->command('cache:prune-stale-tags')->hourly();
    }
}
```

## 🎯 Business Intelligence & Analytics

### Revenue Analytics

**Business Analytics Service**
```php
// app/Services/AnalyticsService.php
class AnalyticsService {
    public static function getRevenueMetrics($period = 'month') {
        $query = BillingTransaction::where('status', 'completed')
                                  ->where('type', 'subscription_payment');

        switch ($period) {
            case 'day':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        return [
            'total_revenue' => $query->sum('amount'),
            'total_transactions' => $query->count(),
            'average_transaction_value' => $query->avg('amount'),
            'revenue_by_plan' => $query->join('subscriptions', 'billing_transactions.subscription_id', '=', 'subscriptions.id')
                                       ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                                       ->selectRaw('subscription_plans.name, SUM(billing_transactions.amount) as revenue')
                                       ->groupBy('subscription_plans.name')
                                       ->get(),
        ];
    }

    public static function getSubscriberMetrics() {
        return [
            'total_subscribers' => Subscription::where('status', 'active')->count(),
            'new_subscribers_this_month' => Subscription::whereMonth('created_at', now()->month)
                                                       ->where('status', 'active')
                                                       ->count(),
            'churn_rate' => self::calculateChurnRate(),
            'mrr' => self::calculateMonthlyRecurringRevenue(),
            'arr' => self::calculateAnnualRecurringRevenue(),
        ];
    }

    private static function calculateChurnRate() {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $activeAtStart = Subscription::where('status', 'active')
                                    ->where('expires_at', '>=', $startOfMonth)
                                    ->count();

        $cancelledThisMonth = Subscription::where('cancelled_at', '>=', $startOfMonth)
                                         ->where('cancelled_at', '<=', $endOfMonth)
                                         ->count();

        if ($activeAtStart === 0) {
            return 0;
        }

        return ($cancelledThisMonth / $activeAtStart) * 100;
    }

    private static function calculateMonthlyRecurringRevenue() {
        return Subscription::where('status', 'active')
                           ->where('billing_cycle', 'monthly')
                           ->sum('amount');
    }

    private static function calculateAnnualRecurringRevenue() {
        return Subscription::where('status', 'active')
                           ->sum('amount') * 12; // Simplified calculation
    }
}
```

## 🚀 Scalability & Growth Strategy

### Business Model Optimization

**Dynamic Pricing Strategy**
```php
// app/Services/DynamicPricingService.php
class DynamicPricingService {
    public static function calculateOptimalPricing() {
        $metrics = AnalyticsService::getSubscriberMetrics();
        $usage = self::getAverageUsageMetrics();

        $suggestions = [];

        // Analyze conversion rates by plan
        $conversionByPlan = DB::table('subscription_plans')
            ->selectRaw('name,
                        COUNT(CASE WHEN status = "active" THEN 1 END) as active_subscribers,
                        COUNT(*) as total_subscribers')
            ->leftJoin('subscriptions', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->get();

        foreach ($conversionByPlan as $plan) {
            $conversionRate = $plan->total_subscribers > 0
                ? ($plan->active_subscribers / $plan->total_subscribers) * 100
                : 0;

            if ($conversionRate < 10) {
                $suggestions[] = [
                    'plan' => $plan->name,
                    'issue' => 'Low conversion rate',
                    'suggestion' => 'Consider reducing price or adding more features',
                    'current_rate' => round($conversionRate, 2) . '%'
                ];
            } elseif ($conversionRate > 80) {
                $suggestions[] = [
                    'plan' => $plan->name,
                    'issue' => 'High conversion rate',
                    'suggestion' => 'Consider increasing price or creating higher tier',
                    'current_rate' => round($conversionRate, 2) . '%'
                ];
            }
        }

        return $suggestions;
    }

    public static function getAverageUsageMetrics() {
        return [
            'avg_messages_per_customer' => DB::table('chats')
                ->selectRaw('workspace_id, COUNT(*) as message_count')
                ->where('type', 'outbound')
                ->whereMonth('created_at', now()->month)
                ->groupBy('workspace_id')
                ->avg('message_count'),

            'avg_contacts_per_customer' => DB::table('contacts')
                ->selectRaw('workspace_id, COUNT(*) as contact_count')
                ->whereNull('deleted_at')
                ->groupBy('workspace_id')
                ->avg('contact_count'),
        ];
    }
}
```

---

**Multi-Tenant SaaS Subscription Business Model** ini menyediakan foundation yang scalable dan robust untuk WhatsApp Business Platform, dengan complete lifecycle management dari user acquisition hingga revenue optimization.