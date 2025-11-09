<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\WhatsappService;
use App\Services\ChatService;
use App\Services\ContactService;
use App\Services\MediaService;
use App\Services\TemplateService;
use App\Services\SubscriptionService;
use App\Models\workspace;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\BusinessProfileService;
use App\Services\WhatsApp\WhatsAppHealthService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Core Services Registration
        $this->app->singleton(ContactService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new ContactService($workspace->id);
        });

        $this->app->singleton(ChatService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new ChatService($workspace->id);
        });

        $this->app->singleton(MediaService::class, function ($app) {
            return new MediaService();
        });

        $this->app->singleton(TemplateService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new TemplateService($workspace->id);
        });

        $this->app->singleton(SubscriptionService::class, function ($app) {
            return new SubscriptionService();
        });

        // WhatsApp Service Registration (Legacy - maintains backward compatibility)
        $this->app->singleton(WhatsappService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new WhatsappService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        // NEW: Individual WhatsApp Services Registration
        $this->app->singleton(MessageSendingService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new MessageSendingService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        $this->app->singleton(TemplateManagementService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new TemplateManagementService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        $this->app->singleton(MediaProcessingService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new MediaProcessingService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        $this->app->singleton(BusinessProfileService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new BusinessProfileService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        $this->app->singleton(WhatsAppHealthService::class, function ($app) {
            $workspace = $app->make('App\Models\Workspace');
            return new WhatsAppHealthService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        // Payment Platform Resolver
        $this->app->singleton(PaymentPlatformResolver::class, function ($app) {
            return new PaymentPlatformResolver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        
        if (!App::environment('local')) {
            URL::forceScheme('https');
        }
    }
}
