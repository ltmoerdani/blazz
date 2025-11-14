<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AutoReplyService;
use App\Services\CampaignService;
use App\Services\UserService;
use App\Services\BillingService;
use App\Services\TeamService;
use App\Services\SettingService;
use App\Services\SecurityService;
use App\Services\WorkspaceService;
use App\Services\ContactProvisioningService;
use App\Services\ContactFieldService;
use App\Services\ChatNoteService;
use App\Services\PerformanceCacheService;
use App\Services\NotificationService;
use App\Services\LangService;
use App\Services\PageService;
use App\Services\AuthService;
use App\Services\RoleService;
use App\Services\CouponService;
use App\Services\TaxService;
use App\Services\TicketService;
use App\Services\EmailService;
use App\Services\PasswordResetService;
use App\Services\SocialLoginService;
use App\Services\ModuleService;
use App\Services\SubscriptionService;
use App\Services\SubscriptionPlanService;
use App\Services\TemplateService;
use App\Helpers\WorkspaceHelper;

class BusinessServiceProvider extends ServiceProvider
{
    /**
     * Workspace resolution moved to WorkspaceHelper to eliminate duplication
     */

    public function register(): void
    {
        // Auto Reply Service
        $this->app->singleton(AutoReplyService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new AutoReplyService(
                $workspace->id,
                $app->make('App\Services\WhatsApp\MessageSendingService'),
                $app->make('App\Services\WhatsApp\MediaProcessingService')
            );
        });

        // Campaign Service
        $this->app->singleton(CampaignService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new CampaignService($workspace->id);
        });

        // User Service - with role support
        $this->app->bind(UserService::class, function ($app) {
            return new UserService('user'); // default role
        });

        // Admin User Service
        $this->app->bind('App\Services\AdminUserService', function ($app) {
            return new UserService('admin');
        });

        // Billing Service
        $this->app->singleton(BillingService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new BillingService($app->make(SubscriptionService::class));
        });

        // Team Service
        $this->app->singleton(TeamService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new TeamService($workspace->id);
        });

        // Setting Service
        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService();
        });

        // Security Service
        $this->app->singleton(SecurityService::class, function ($app) {
            return new SecurityService();
        });

        // Workspace Service
        $this->app->singleton(WorkspaceService::class, function ($app) {
            return new WorkspaceService();
        });

        // Contact Provisioning Service
        $this->app->singleton(ContactProvisioningService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new ContactProvisioningService($workspace->id);
        });

        // Contact Field Service
        $this->app->singleton(ContactFieldService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new ContactFieldService($workspace->id);
        });

        // Chat Note Service
        $this->app->singleton(ChatNoteService::class, function ($app) {
            return new ChatNoteService();
        });

        // Performance Cache Service
        $this->app->singleton(PerformanceCacheService::class, function ($app) {
            return new PerformanceCacheService();
        });

        // Notification Service
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        // Lang Service
        $this->app->singleton(LangService::class, function ($app) {
            return new LangService();
        });

        // Page Service
        $this->app->singleton(PageService::class, function ($app) {
            return new PageService();
        });

        // Auth Service
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService($app->make('App\Models\Role'));
        });

        // Role Service
        $this->app->singleton(RoleService::class, function ($app) {
            return new RoleService();
        });

        // Coupon Service
        $this->app->singleton(CouponService::class, function ($app) {
            return new CouponService();
        });

        // Tax Service
        $this->app->singleton(TaxService::class, function ($app) {
            return new TaxService();
        });

        // Ticket Service
        $this->app->singleton(TicketService::class, function ($app) {
            return new TicketService();
        });

        // Email Service
        $this->app->singleton(EmailService::class, function ($app) {
            return new EmailService();
        });

        // Password Reset Service
        $this->app->singleton(PasswordResetService::class, function ($app) {
            return new PasswordResetService();
        });

        // Social Login Service
        $this->app->singleton(SocialLoginService::class, function ($app) {
            return new SocialLoginService();
        });

        // Module Service
        $this->app->singleton(ModuleService::class, function ($app) {
            return new ModuleService();
        });

        // Subscription Service moved to SubscriptionServiceProvider to avoid duplication

        // Subscription Plan Service
        $this->app->singleton(SubscriptionPlanService::class, function ($app) {
            return new SubscriptionPlanService();
        });

        // Template Service moved to UtilityServiceProvider to avoid duplication
    }
}