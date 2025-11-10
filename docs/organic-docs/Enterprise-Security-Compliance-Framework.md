# 🔐 Enterprise Security & Compliance Framework - Complete Implementation Guide

## 📋 Security Overview

**Blazz Platform** mengimplementasikan **Defense-in-Depth Security Architecture** yang dirancang untuk memenuhi enterprise-grade security requirements dan international compliance standards. Framework ini mencakup multi-layer security controls, comprehensive audit trails, dan automated compliance management untuk melindungi data pelanggan dan memastikan business continuity.

## 🏗️ Security Architecture Overview

### Multi-Layer Security Model

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                       │
│  • XSS Protection • CSRF Protection • Content Security Policy │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                   APPLICATION LAYER                         │
│  • Authentication • Authorization • Input Validation       │
│  • Rate Limiting • API Security • Session Management        │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                     DATA LAYER                              │
│  • Encryption at Rest • Data Masking • Backup Encryption    │
│  • Database Security • Access Controls • Audit Logging      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                  INFRASTRUCTURE LAYER                       │
│  • Network Security • DDoS Protection • SSL/TLS            │
│  • Firewall Rules • VPN Access • Intrusion Detection        │
└─────────────────────────────────────────────────────────────┘
```

## 🔐 Authentication & Authorization

### Multi-Guard Authentication System

**Laravel Sanctum + Custom Guards**
```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
        'hash' => false,
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admin_users',
    ],
    'workspace_api' => [
        'driver' => 'sanctum',
        'provider' => 'workspace_users',
        'hash' => false,
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'admin_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\UserAdmin::class,
    ],
    'workspace_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\Team::class,
    ],
],
```

**Advanced User Authentication Model**
```php
// app/Models/User.php
class User extends Authenticatable implements MustVerifyEmail {
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar',
        'role',
        'phone',
        'address',
        'tfa_secret',
        'tfa_enabled',
        'last_login_at',
        'last_login_ip',
        'login_attempts',
        'locked_until'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'tfa_secret',
        'tfa_recovery_codes'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'tfa_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'tfa_recovery_codes' => 'array'
    ];

    // Enhanced security methods
    public function isLocked() {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function hasTooManyLoginAttempts() {
        return $this->login_attempts >= 5;
    }

    public function incrementLoginAttempts() {
        $this->increment('login_attempts');

        if ($this->hasTooManyLoginAttempts()) {
            $this->update(['locked_until' => now()->addMinutes(15)]);
        }
    }

    public function resetLoginAttempts() {
        $this->update([
            'login_attempts' => 0,
            'locked_until' => null
        ]);
    }

    public function recordSuccessfulLogin($ipAddress) {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'login_attempts' => 0,
            'locked_until' => null
        ]);

        // Log successful login
        activity()
            ->causedBy($this)
            ->performedOn($this)
            ->log('Successful login from IP: ' . $ipAddress);
    }
}
```

### Two-Factor Authentication (2FA) Implementation

**TOTP-Based 2FA System**
```php
// app/Services/Security/TwoFactorAuthService.php
class TwoFactorAuthService {
    public function generateSecretKey() {
        return Google2FA::generateSecretKey();
    }

    public function generateQRCode($user, $secretKey) {
        $companyName = config('app.name');
        $email = $user->email;

        $qrCodeUrl = Google2FA::getQRCodeUrl(
            $companyName,
            $email,
            $secretKey
        );

        return QrCode::format('png')->size(200)->generate($qrCodeUrl);
    }

    public function verifyCode($user, $code) {
        if (!$user->tfa_enabled || !$user->tfa_secret) {
            return false;
        }

        $isValid = Google2FA::verifyKey(
            $user->tfa_secret,
            $code,
            config('auth.tfa.window', 1)
        );

        if ($isValid) {
            // Log successful 2FA verification
            activity()
                ->causedBy($user)
                ->log('2FA verification successful');
        }

        return $isValid;
    }

    public function generateRecoveryCodes() {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = Str::random(10) . '-' . Str::random(10);
        }
        return $codes;
    }

    public function verifyRecoveryCode($user, $code) {
        if (!$user->tfa_recovery_codes) {
            return false;
        }

        $codes = $user->tfa_recovery_codes;

        if (in_array($code, $codes)) {
            // Remove used recovery code
            $remainingCodes = array_diff($codes, [$code]);
            $user->update(['tfa_recovery_codes' => array_values($remainingCodes)]);

            // Log recovery code usage
            activity()
                ->causedBy($user)
                ->log('2FA recovery code used');

            return true;
        }

        return false;
    }
}
```

**2FA Middleware Implementation**
```php
// app/Http/Middleware/TwoFactorAuthentication.php
class TwoFactorAuthentication {
    public function handle($request, Closure $next) {
        $user = auth()->user();

        if ($user && $user->tfa_enabled && !$session('2fa_verified')) {
            // Verify 2FA code
            if ($request->is('api/*')) {
                return response()->json(['error' => '2FA required'], 423);
            }

            return redirect()->route('2fa.verify');
        }

        return $next($request);
    }
}
```

## 🔑 Advanced Role-Based Access Control (RBAC)

### Hierarchical Permission System

**Role & Permission Models**
```php
// app/Models/Role.php
class Role extends Model {
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'is_system' => 'boolean',
        'permissions' => 'array'
    ];

    public function users() {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function permissions() {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function hasPermission($permission) {
        return $this->permissions()->where('name', $permission)->exists();
    }

    public function givePermissionTo($permission) {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function revokePermissionTo($permission) {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }

        $this->permissions()->detach($permission->id);
    }
}

// app/Models/Permission.php
class Permission extends Model {
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'is_system' => 'boolean'
    ];

    public function roles() {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
```

**Dynamic Permission Checking Service**
```php
// app/Services/Security/PermissionService.php
class PermissionService {
    public static function hasPermission($user, $permission, $resource = null) {
        // Cache user permissions for performance
        $cacheKey = "user_permissions:{$user->id}";
        $userPermissions = Cache::remember($cacheKey, 3600, function () use ($user) {
            return $user->roles()->with('permissions')->get()
                ->pluck('permissions.*.name')
                ->flatten()
                ->unique()
                ->toArray();
        });

        if (!in_array($permission, $userPermissions)) {
            return false;
        }

        // Check resource-level permissions if specified
        if ($resource) {
            return self::checkResourceAccess($user, $permission, $resource);
        }

        return true;
    }

    private static function checkResourceAccess($user, $permission, $resource) {
        switch ($permission) {
            case 'view_contacts':
                return self::checkContactAccess($user, $resource);
            case 'edit_contacts':
                return self::checkContactEditAccess($user, $resource);
            case 'manage_campaigns':
                return self::checkCampaignAccess($user, $resource);
            case 'view_analytics':
                return self::checkAnalyticsAccess($user, $resource);
            case 'manage_users':
                return self::checkUserManagementAccess($user, $resource);
            default:
                return true;
        }
    }

    private static function checkContactAccess($user, $contact) {
        // Super admin can access all contacts
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Workspace owners can access all workspace contacts
        if ($user->hasRole('workspace_owner')) {
            return $contact->workspace_id === $user->current_workspace_id;
        }

        // Agents can only access assigned contacts
        if ($user->hasRole('agent')) {
            return $contact->assigned_agent_id === $user->id;
        }

        // Managers can access team contacts
        if ($user->hasRole('manager')) {
            return $contact->team_id === $user->team_id;
        }

        return false;
    }

    public static function createPermissionCache($user) {
        $permissions = $user->roles()->with('permissions')->get()
            ->pluck('permissions.*.name')
            ->flatten()
            ->unique()
            ->toArray();

        Cache::put("user_permissions:{$user->id}", $permissions, 3600);
    }

    public static function clearPermissionCache($user) {
        Cache::forget("user_permissions:{$user->id}");
    }
}
```

### Workspace-Level Access Control

**Workspace Access Management**
```php
// app/Models/Team.php
class Team extends Model {
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'permissions' => 'array',
        'last_active_at' => 'datetime'
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function hasWorkspacePermission($permission) {
        $workspacePermissions = $this->permissions ?? [];
        return in_array($permission, $workspacePermissions);
    }

    public function scopeWithPermission($query, $permission) {
        return $query->whereJsonContains('permissions', $permission);
    }

    public function getActivityLogs() {
        return $this->hasManyThrough(
            ActivityLog::class,
            User::class,
            'id',
            'causer_id'
        )->where('workspace_id', $this->workspace_id);
    }
}
```

## 🛡️ Data Protection & Encryption

### End-to-End Encryption Implementation

**Encryption Service**
```php
// app/Services/Security/EncryptionService.php
class EncryptionService {
    private $encryptionKey;

    public function __construct() {
        $this->encryptionKey = config('app.encryption_key');
    }

    public function encryptSensitiveData($data) {
        if (empty($data)) {
            return $data;
        }

        try {
            $encrypted = openssl_encrypt(
                $data,
                'AES-256-GCM',
                $this->encryptionKey,
                0,
                substr($this->encryptionKey, 0, 16),
                $tag
            );

            return base64_encode($encrypted . '::' . base64_encode($tag));
        } catch (Exception $e) {
            Log::error('Data encryption failed', ['error' => $e->getMessage()]);
            throw new EncryptionException('Failed to encrypt sensitive data');
        }
    }

    public function decryptSensitiveData($encryptedData) {
        if (empty($encryptedData)) {
            return $encryptedData;
        }

        try {
            $parts = explode('::', $encryptedData);
            if (count($parts) !== 2) {
                throw new InvalidArgumentException('Invalid encrypted data format');
            }

            $encrypted = $parts[0];
            $tag = base64_decode($parts[1]);

            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-GCM',
                $this->encryptionKey,
                0,
                substr($this->encryptionKey, 0, 16),
                $tag
            );

            if ($decrypted === false) {
                throw new DecryptionException('Failed to decrypt data');
            }

            return $decrypted;
        } catch (Exception $e) {
            Log::error('Data decryption failed', ['error' => $e->getMessage()]);
            throw new DecryptionException('Failed to decrypt sensitive data');
        }
    }

    public function encryptPII($data) {
        $piiFields = ['email', 'phone', 'address', 'first_name', 'last_name'];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, $piiFields)) {
                    $data[$key] = $this->encryptSensitiveData($value);
                }
            }
        } elseif (is_string($data)) {
            return $this->encryptSensitiveData($data);
        }

        return $data;
    }
}
```

**Database Encryption Integration**
```php
// app/Models/Traits/EncryptsAttributes.php
trait EncryptsAttributes {
    public function setAttribute($key, $value) {
        if ($this->isEncryptable($key)) {
            $value = app(EncryptionService::class)->encryptSensitiveData($value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key) {
        $value = parent::getAttribute($key);

        if ($this->isEncryptable($key) && !is_null($value)) {
            try {
                return app(EncryptionService::class)->decryptSensitiveData($value);
            } catch (DecryptionException $e) {
                Log::warning("Failed to decrypt attribute {$key}", [
                    'model' => static::class,
                    'id' => $this->id
                ]);
                return '[Encrypted]';
            }
        }

        return $value;
    }

    protected function isEncryptable($key) {
        return in_array($key, $this->encryptable ?? []);
    }
}
```

### Secure File Storage & Transfer

**File Security Service**
```php
// app/Services/Security/FileSecurityService.php
class FileSecurityService {
    public function secureUpload($file, $workspaceId, $type = 'general') {
        // Validate file type and size
        $this->validateFileSecurity($file);

        // Generate secure filename
        $filename = $this->generateSecureFilename($file, $workspaceId);

        // Scan for malware
        $this->scanFileForMalware($file);

        // Encrypt file content if sensitive
        if ($this->isSensitiveFileType($file, $type)) {
            $file = $this->encryptFile($file);
        }

        // Upload to secure storage
        $path = $this->uploadToSecureStorage($file, $filename, $workspaceId);

        // Create file record with security metadata
        return $this->createSecureFileRecord($path, $workspaceId, $type);
    }

    private function validateFileSecurity($file) {
        $allowedMimes = config('security.allowed_mime_types');
        $maxSize = config('security.max_file_size', 10 * 1024 * 1024); // 10MB

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new SecurityException('File type not allowed');
        }

        if ($file->getSize() > $maxSize) {
            throw new SecurityException('File size exceeds limit');
        }

        // Check for embedded scripts
        if ($this->containsEmbeddedScripts($file)) {
            throw new SecurityException('File contains potentially malicious content');
        }
    }

    private function scanFileForMalware($file) {
        if (!config('security.malware_scanning_enabled')) {
            return true;
        }

        $scanner = new ClamAVScanner();
        $result = $scanner->scan($file->getPathname());

        if ($result->isInfected()) {
            throw new SecurityException('Malware detected in uploaded file');
        }

        return true;
    }

    private function generateSecureFilename($file, $workspaceId) {
        $extension = $file->getClientOriginalExtension();
        $random = Str::random(32);
        $timestamp = now()->format('YmdHis');

        return "{$workspaceId}/{$timestamp}_{$random}.{$extension}";
    }
}
```

## 📝 Comprehensive Audit & Logging System

### Activity Logging Framework

**Advanced Activity Logger**
```php
// app/Models/ActivityLog.php
class ActivityLog extends Model {
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'properties' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    public function causer() {
        return $this->morphTo();
    }

    public function subject() {
        return $this->morphTo();
    }

    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace($query, $workspaceId) {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForUser($query, $userId) {
        return $query->where('causer_type', User::class)
                    ->where('causer_id', $userId);
    }

    public function scopeOfType($query, $type) {
        return $query->where('log_name', $type);
    }

    public function getHumanReadableActionAttribute() {
        return Str::title(str_replace('_', ' ', $this->description));
    }
}
```

**Security Event Logger**
```php
// app/Services/Security/SecurityEventLogger.php
class SecurityEventLogger {
    public static function logSecurityEvent($event, $context = []) {
        $logEntry = ActivityLog::create([
            'log_name' => 'security',
            'description' => $event,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'causer_type' => $context['causer_type'] ?? 'system',
            'causer_id' => $context['causer_id'] ?? null,
            'properties' => $context,
            'workspace_id' => $context['workspace_id'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now()
        ]);

        // Trigger real-time security alerts
        if (self::isCriticalSecurityEvent($event)) {
            self::triggerSecurityAlert($logEntry, $context);
        }

        return $logEntry;
    }

    private static function isCriticalSecurityEvent($event) {
        $criticalEvents = [
            'multiple_failed_login_attempts',
            'unauthorized_access_attempt',
            'privilege_escalation_attempt',
            'data_export_attempt',
            'suspicious_api_usage',
            'malware_detected',
            'data_breach_attempt'
        ];

        return in_array($event, $criticalEvents);
    }

    private static function triggerSecurityAlert($logEntry, $context) {
        // Send to security team
        Notification::route('slack', config('security.slack_webhook'))
            ->notify(new SecurityAlert($logEntry, $context));

        // Send email to security administrators
        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'security_admin');
        })->get();

        foreach ($admins as $admin) {
            $admin->notify(new SecurityIncidentAlert($logEntry));
        }

        // Log to external security monitoring
        if (config('security.external_monitoring.enabled')) {
            Http::post(config('security.external_monitoring.webhook'), [
                'event' => $logEntry->description,
                'severity' => 'critical',
                'timestamp' => $logEntry->created_at->toISOString(),
                'context' => $context
            ]);
        }
    }
}
```

### Automated Threat Detection

**Anomaly Detection System**
```php
// app/Services/Security/AnomalyDetectionService.php
class AnomalyDetectionService {
    public function detectAnomalies($userId, $workspaceId = null) {
        $anomalies = [];

        // Check for unusual login patterns
        $loginAnomalies = $this->detectLoginAnomalies($userId);
        $anomalies = array_merge($anomalies, $loginAnomalies);

        // Check for unusual API usage
        $apiAnomalies = $this->detectAPIAnomalies($userId, $workspaceId);
        $anomalies = array_merge($anomalies, $apiAnomalies);

        // Check for unusual data access patterns
        $dataAnomalies = $this->detectDataAccessAnomalies($userId, $workspaceId);
        $anomalies = array_merge($anomalies, $dataAnomalies);

        // Process detected anomalies
        foreach ($anomalies as $anomaly) {
            $this->handleAnomaly($anomaly, $userId, $workspaceId);
        }

        return $anomalies;
    }

    private function detectLoginAnomalies($userId) {
        $anomalies = [];
        $recentLogins = ActivityLog::where('causer_type', User::class)
            ->where('causer_id', $userId)
            ->where('log_name', 'auth')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        // Check for logins from unusual locations
        $knownIPs = $recentLogins->pluck('ip_address')->unique()->toArray();
        $currentIP = request()->ip();

        if (!in_array($currentIP, $knownIPs) && count($knownIPs) > 0) {
            $anomalies[] = [
                'type' => 'unusual_login_location',
                'severity' => 'medium',
                'description' => "Login from new IP address: {$currentIP}",
                'data' => [
                    'new_ip' => $currentIP,
                    'known_ips' => $knownIPs
                ]
            ];
        }

        // Check for login time anomalies
        $loginHours = $recentLogins->map(function ($login) {
            return Carbon::parse($login->created_at)->hour;
        })->toArray();

        $currentHour = now()->hour;
        if (!in_array($currentHour, $loginHours) && count($loginHours) > 0) {
            $anomalies[] = [
                'type' => 'unusual_login_time',
                'severity' => 'low',
                'description' => "Login at unusual time: {$currentHour}:00",
                'data' => [
                    'current_hour' => $currentHour,
                    'usual_hours' => $loginHours
                ]
            ];
        }

        return $anomalies;
    }

    private function detectAPIAnomalies($userId, $workspaceId) {
        $anomalies = [];
        $window = now()->subMinutes(5);

        $recentRequests = ActivityLog::where('causer_type', User::class)
            ->where('causer_id', $userId)
            ->where('log_name', 'api')
            ->where('created_at', '>=', $window)
            ->count();

        $avgRequestsPerMinute = $this->getAverageAPIRequests($userId);
        $threshold = $avgRequestsPerMinute * 5; // 5x normal usage

        if ($recentRequests > $threshold) {
            $anomalies[] = [
                'type' => 'excessive_api_usage',
                'severity' => 'high',
                'description' => "Unusually high API request rate: {$recentRequests} requests in 5 minutes",
                'data' => [
                    'current_requests' => $recentRequests,
                    'threshold' => $threshold,
                    'average' => $avgRequestsPerMinute
                ]
            ];
        }

        return $anomalies;
    }

    private function handleAnomaly($anomaly, $userId, $workspaceId) {
        // Log the anomaly
        SecurityEventLogger::logSecurityEvent('anomaly_detected', [
            'anomaly_type' => $anomaly['type'],
            'severity' => $anomaly['severity'],
            'description' => $anomaly['description'],
            'data' => $anomaly['data'],
            'user_id' => $userId,
            'workspace_id' => $workspaceId
        ]);

        // Take automated actions based on severity
        switch ($anomaly['severity']) {
            case 'high':
                $this->handleHighSeverityAnomaly($anomaly, $userId);
                break;
            case 'medium':
                $this->handleMediumSeverityAnomaly($anomaly, $userId);
                break;
            case 'low':
                $this->handleLowSeverityAnomaly($anomaly, $userId);
                break;
        }
    }

    private function handleHighSeverityAnomaly($anomaly, $userId) {
        // Temporarily lock account
        $user = User::find($userId);
        $user->update(['locked_until' => now()->addMinutes(30)]);

        // Force logout from all sessions
        DB::table('sessions')->where('user_id', $userId)->delete();

        // Notify security team
        SecurityEventLogger::logSecurityEvent('account_auto_locked', [
            'user_id' => $userId,
            'reason' => $anomaly['description'],
            'locked_until' => now()->addMinutes(30)
        ]);
    }
}
```

## 🌍 International Compliance Framework

### GDPR Compliance Implementation

**Data Subject Rights Management**
```php
// app/Services/Compliance/GDPRService.php
class GDPRService {
    public function handleDataSubjectRequest($type, $contactId, $verificationData) {
        // Verify request authenticity
        if (!$this->verifyRequestAuthenticity($contactId, $verificationData)) {
            throw new UnauthorizedException('Unable to verify data subject identity');
        }

        switch ($type) {
            case 'access':
                return $this->exportUserData($contactId);
            case 'rectification':
                return $this->rectifyUserData($contactId, $verificationData['corrections']);
            case 'erasure':
                return $this->eraseUserData($contactId);
            case 'portability':
                return $this->exportPortableData($contactId);
            case 'restriction':
                return $this->restrictProcessing($contactId);
            default:
                throw new InvalidArgumentException('Invalid request type');
        }
    }

    public function exportUserData($contactId) {
        $contact = Contact::with([
            'chats',
            'campaignLogs',
            'contactGroups',
            'notes',
            'tickets'
        ])->find($contactId);

        $exportData = [
            'personal_information' => [
                'name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'address' => $contact->address,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at
            ],
            'communication_history' => $contact->chats->map(function ($chat) {
                return [
                    'message' => $chat->message,
                    'type' => $chat->type,
                    'timestamp' => $chat->created_at,
                    'sender' => $chat->user_id ? 'agent' : 'contact'
                ];
            }),
            'campaign_interactions' => $contact->campaignLogs,
            'support_tickets' => $contact->tickets,
            'contact_groups' => $contact->contactGroups->pluck('name'),
            'notes' => $contact->notes
        ];

        // Create export record
        GDPRExport::create([
            'contact_id' => $contactId,
            'request_type' => 'access',
            'data' => json_encode($exportData),
            'status' => 'completed',
            'expires_at' => now()->addDays(30)
        ]);

        // Log export activity
        SecurityEventLogger::logSecurityEvent('data_exported', [
            'contact_id' => $contactId,
            'request_type' => 'gdpr_access',
            'data_size' => strlen(json_encode($exportData))
        ]);

        return $exportData;
    }

    public function eraseUserData($contactId) {
        DB::transaction(function () use ($contactId) {
            $contact = Contact::find($contactId);

            // Anonymize personal data
            $contact->update([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => 'deleted-' . $contactId . '@deleted.com',
                'phone' => '0000000000',
                'address' => null,
                'deleted_at' => now(),
                'gdpr_erased_at' => now()
            ]);

            // Anonymize messages
            $contact->chats()->update([
                'message' => '[Message deleted per GDPR request]',
                'deleted_at' => now()
            ]);

            // Delete sensitive associations
            $contact->notes()->delete();
            $contact->contactGroups()->detach();

            // Log erasure activity
            SecurityEventLogger::logSecurityEvent('data_erased', [
                'contact_id' => $contactId,
                'request_type' => 'gdpr_erasure'
            ]);
        });
    }

    private function verifyRequestAuthenticity($contactId, $verificationData) {
        $contact = Contact::find($contactId);

        // Verify through email or phone
        if (isset($verificationData['email_code'])) {
            return $this->verifyEmailCode($contact->email, $verificationData['email_code']);
        }

        if (isset($verificationData['sms_code'])) {
            return $this->verifySMSCode($contact->phone, $verificationData['sms_code']);
        }

        return false;
    }
}
```

### Data Processing Records Management

**ROPA (Records of Processing Activities)**
```php
// app/Models/DataProcessingRecord.php
class DataProcessingRecord extends Model {
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'purposes' => 'array',
        'data_categories' => 'array',
        'recipients' => 'array',
        'retention_period' => 'array',
        'security_measures' => 'array'
    ];

    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function getLegalBasisTextAttribute() {
        $bases = [
            'consent' => 'Data subject has given explicit consent',
            'contract' => 'Processing is necessary for the performance of a contract',
            'legal_obligation' => 'Processing is necessary for compliance with legal obligation',
            'vital_interests' => 'Processing is necessary to protect vital interests',
            'public_task' => 'Processing is necessary for the performance of a task in the public interest',
            'legitimate_interests' => 'Processing is necessary for the purposes of legitimate interests'
        ];

        return $bases[$this->legal_basis] ?? 'Unknown legal basis';
    }

    public function getRetentionPeriodTextAttribute() {
        if (isset($this->retention_period['type']) && $this->retention_period['type'] === 'custom') {
            return $this->retention_period['description'];
        }

        return $this->retention_period['type'] ?? 'Not specified';
    }
}
```

### Cookie Consent Management

**Cookie Consent System**
```php
// app/Http/Controllers/CookieConsentController.php
class CookieConsentController extends Controller {
    public function showConsentBanner() {
        $consentCategories = [
            'necessary' => [
                'name' => 'Essential Cookies',
                'description' => 'These cookies are necessary for the website to function and cannot be switched off.',
                'required' => true
            ],
            'analytics' => [
                'name' => 'Analytics Cookies',
                'description' => 'These cookies allow us to count visits and traffic sources.',
                'required' => false
            ],
            'marketing' => [
                'name' => 'Marketing Cookies',
                'description' => 'These cookies are used to track visitors across websites.',
                'required' => false
            ],
            'functional' => [
                'name' => 'Functional Cookies',
                'description' => 'These cookies enable enhanced functionality and personalization.',
                'required' => false
            ]
        ];

        return response()->json([
            'categories' => $consentCategories,
            'privacy_policy_url' => route('privacy.policy'),
            'cookie_policy_url' => route('cookie.policy')
        ]);
    }

    public function saveConsent(Request $request) {
        $validated = $request->validate([
            'consents' => 'required|array',
            'consents.*' => 'boolean'
        ]);

        // Generate consent ID
        $consentId = Str::uuid();

        // Save consent record
        CookieConsent::create([
            'consent_id' => $consentId,
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'consents' => $validated['consents'],
            'timestamp' => now()
        ]);

        // Set consent cookies
        foreach ($validated['consents'] as $category => $consent) {
            cookie()->queue("cookie_consent_{$category}", $consent, 365 * 24 * 60);
        }

        // Set main consent cookie
        cookie()->queue('cookie_consent_given', true, 365 * 24 * 60);
        cookie()->queue('cookie_consent_id', $consentId, 365 * 24 * 60);

        return response()->json([
            'success' => true,
            'consent_id' => $consentId
        ]);
    }
}
```

## 🔒 Security Testing & Vulnerability Management

### Automated Security Testing

**Security Test Suite**
```php
// tests/Feature/SecurityTest.php
class SecurityTest extends TestCase {
    use RefreshDatabase;

    public function test_sql_injection_prevention() {
        $maliciousInput = "'; DROP TABLE users; --";

        $response = $this->post('/api/contacts', [
            'name' => $maliciousInput,
            'email' => 'test@example.com'
        ]);

        $this->assertDatabaseCount('users', 0); // Users table should still exist
        $response->assertStatus(422); // Validation should catch it
    }

    public function test_xss_prevention() {
        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->post('/api/messages', [
            'message' => $xssPayload,
            'contact_id' => 1
        ]);

        $message = Message::first();
        $this->assertStringNotContainsString('<script>', $message->message);
    }

    public function test_authorization_bypass_prevention() {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contact = Contact::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->delete("/api/contacts/{$contact->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_rate_limiting() {
        $user = User::factory()->create();

        // Make 100 requests rapidly
        $responses = collect(range(1, 100))->map(function () use ($user) {
            return $this->actingAs($user)
                ->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        });

        // Should be rate limited after many attempts
        $this->assertContains(429, $responses->pluck('status')->all());
    }
}
```

### Vulnerability Scanner Integration

**Security Scanner Service**
```php
// app/Services/Security/VulnerabilityScanner.php
class VulnerabilityScanner {
    public function scanDependencies() {
        $composerLock = json_decode(file_get_contents(base_path('composer.lock')), true);
        $vulnerabilities = [];

        foreach ($composerLock['packages'] as $package) {
            $advisories = $this->checkSecurityAdvisories($package['name'], $package['version']);

            if (!empty($advisories)) {
                $vulnerabilities[] = [
                    'package' => $package['name'],
                    'version' => $package['version'],
                    'advisories' => $advisories,
                    'severity' => $this->calculateSeverity($advisories)
                ];
            }
        }

        return $vulnerabilities;
    }

    public function scanApplicationCode() {
        $issues = [];

        // Check for hardcoded secrets
        $secretIssues = $this->scanForHardcodedSecrets();
        $issues = array_merge($issues, $secretIssues);

        // Check for SQL injection vulnerabilities
        $sqlIssues = $this->scanForSQLVulnerabilities();
        $issues = array_merge($issues, $sqlIssues);

        // Check for XSS vulnerabilities
        $xssIssues = $this->scanForXSSVulnerabilities();
        $issues = array_merge($issues, $xssIssues);

        return $issues;
    }

    private function scanForHardcodedSecrets() {
        $patterns = [
            '/password\s*=\s*["\'][^"\']+["\']/',
            '/api_key\s*=\s*["\'][^"\']+["\']/',
            '/secret\s*=\s*["\'][^"\']+["\']/',
            '/token\s*=\s*["\'][^"\']+["\']/'
        ];

        $issues = [];
        $files = $this->getPhpFiles();

        foreach ($files as $file) {
            $content = file_get_contents($file);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $issues[] = [
                        'type' => 'hardcoded_secret',
                        'file' => $file,
                        'line' => $this->findLineNumber($content, $pattern),
                        'severity' => 'high'
                    ];
                }
            }
        }

        return $issues;
    }

    public function generateSecurityReport() {
        $dependencyVulns = $this->scanDependencies();
        $codeIssues = $this->scanApplicationCode();

        $report = [
            'timestamp' => now()->toISOString(),
            'summary' => [
                'total_vulnerabilities' => count($dependencyVulns) + count($codeIssues),
                'high_severity' => $this->countHighSeverity($dependencyVulns, $codeIssues),
                'medium_severity' => $this->countMediumSeverity($dependencyVulns, $codeIssues),
                'low_severity' => $this->countLowSeverity($dependencyVulns, $codeIssues)
            ],
            'dependencies' => $dependencyVulns,
            'code_issues' => $codeIssues,
            'recommendations' => $this->generateRecommendations($dependencyVulns, $codeIssues)
        ];

        // Save report
        SecurityReport::create([
            'report_data' => json_encode($report),
            'generated_at' => now()
        ]);

        return $report;
    }
}
```

## 🚀 Incident Response & Recovery

### Security Incident Response

**Incident Management System**
```php
// app/Models/SecurityIncident.php
class SecurityIncident extends Model {
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
        'timeline' => 'array'
    ];

    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }

    public function assignee() {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reporter() {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function getSeverityColorAttribute() {
        $colors = [
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue'
        ];

        return $colors[$this->severity] ?? 'gray';
    }

    public function getStatusBadgeAttribute() {
        $badges = [
            'open' => '<span class="bg-red-100 text-red-800 px-2 py-1 rounded">Open</span>',
            'investigating' => '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Investigating</span>',
            'resolved' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded">Resolved</span>',
            'closed' => '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded">Closed</span>'
        ];

        return $badges[$this->status] ?? $badges['open'];
    }
}
```

**Automated Incident Response**
```php
// app/Services/Security/IncidentResponseService.php
class IncidentResponseService {
    public function handleSecurityIncident($incidentType, $context) {
        // Create incident record
        $incident = SecurityIncident::create([
            'title' => $this->generateIncidentTitle($incidentType, $context),
            'description' => $this->generateIncidentDescription($incidentType, $context),
            'severity' => $this->determineSeverity($incidentType, $context),
            'status' => 'open',
            'detected_at' => now(),
            'workspace_id' => $context['workspace_id'] ?? null,
            'reported_by' => $context['user_id'] ?? null,
            'metadata' => $context
        ]);

        // Trigger automated response actions
        $this->executeAutomatedResponse($incident, $incidentType, $context);

        // Notify security team
        $this->notifySecurityTeam($incident);

        return $incident;
    }

    private function executeAutomatedResponse($incident, $incidentType, $context) {
        switch ($incidentType) {
            case 'brute_force_attack':
                $this->handleBruteForceAttack($context);
                break;
            case 'data_breach_attempt':
                $this->handleDataBreachAttempt($context);
                break;
            case 'malware_detected':
                $this->handleMalwareDetection($context);
                break;
            case 'unauthorized_access':
                $this->handleUnauthorizedAccess($context);
                break;
        }

        // Update incident timeline
        $this->updateIncidentTimeline($incident, 'Automated response executed');
    }

    private function handleBruteForceAttack($context) {
        // Block IP address
        $this->blockIPAddress($context['ip_address'], 3600); // 1 hour

        // Lock affected accounts
        if (isset($context['user_id'])) {
            $user = User::find($context['user_id']);
            $user->update(['locked_until' => now()->addHours(1)]);
        }

        // Increase rate limiting
        $this->increaseRateLimit($context['ip_address']);
    }

    private function handleDataBreachAttempt($context) {
        // Immediately suspend affected API keys
        if (isset($context['api_key'])) {
            $apiKey = ApiKey::where('key', $context['api_key'])->first();
            if ($apiKey) {
                $apiKey->update(['status' => 'suspended']);
            }
        }

        // Force logout suspicious sessions
        if (isset($context['user_id'])) {
            DB::table('sessions')
                ->where('user_id', $context['user_id'])
                ->where('ip_address', '!=', $context['user_normal_ip'])
                ->delete();
        }

        // Enable enhanced monitoring
        $this->enableEnhancedMonitoring($context['workspace_id']);
    }

    private function notifySecurityTeam($incident) {
        // Send to Slack
        Notification::route('slack', config('security.slack_webhook'))
            ->notify(new SecurityIncidentAlert($incident));

        // Send email to security team
        $securityTeam = User::whereHas('roles', function ($query) {
            $query->where('name', 'security_admin');
        })->get();

        foreach ($securityTeam as $admin) {
            $admin->notify(new SecurityIncidentEmail($incident));
        }

        // Create incident ticket in external system
        if (config('security.ticketing.enabled')) {
            $this->createIncidentTicket($incident);
        }
    }
}
```

---

**Enterprise Security & Compliance Framework** ini menyediakan comprehensive security implementation yang memenuhi international standards dan best practices untuk enterprise WhatsApp Business Platform, dengan fokus pada data protection, threat detection, dan automated compliance management.