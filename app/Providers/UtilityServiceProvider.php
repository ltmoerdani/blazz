<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TemplateService;
use App\Services\CouponService;
use App\Services\TaxService;
use App\Services\TicketService;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Services\PasswordResetService;
use App\Services\SocialLoginService;
use App\Services\ModuleService;
use App\Services\TestimonialService;
use App\Services\FaqService;
use App\Services\SubscriptionPlanService;
use App\Services\StripeService;
use App\Services\PayPalService;
use App\Services\RazorPayService;
use App\Services\FlutterwaveService;
use App\Services\PayStackService;
use App\Services\CoinbaseService;
use App\Models\workspace;

class UtilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Template Service - requires runtime workspace context, not registered as singleton
        // Keeping manual instantiation for now due to session-based workspace dependency

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

        // Notification Service
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
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

        // Testimonial Service
        $this->app->singleton(TestimonialService::class, function ($app) {
            return new TestimonialService();
        });

        // FAQ Service
        $this->app->singleton(FaqService::class, function ($app) {
            return new FaqService();
        });

        // Subscription Plan Service
        $this->app->singleton(SubscriptionPlanService::class, function ($app) {
            return new SubscriptionPlanService();
        });

        // NOTE: Core services (ChatService, ContactService, MediaService, SubscriptionService)
        // are registered in AppServiceProvider to avoid duplication

        // Payment Services
        $this->app->singleton(StripeService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new StripeService($workspace->id);
        });

        $this->app->singleton(PayPalService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new PayPalService($workspace->id);
        });

        $this->app->singleton(RazorPayService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new RazorPayService($workspace->id);
        });

        $this->app->singleton(FlutterwaveService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new FlutterwaveService($workspace->id);
        });

        $this->app->singleton(PayStackService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new PayStackService($workspace->id);
        });

        $this->app->singleton(CoinbaseService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new CoinbaseService($workspace->id);
        });
    }
}