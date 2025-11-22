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
use App\Helpers\WorkspaceHelper;
use App\Resolvers\PaymentPlatformResolver;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\BusinessProfileService;
use App\Services\WhatsApp\WhatsAppHealthService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Workspace resolution moved to WorkspaceHelper to eliminate duplication
     */

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Core Services Registration
        $this->app->singleton(ContactService::class, function ($app) {
            return new ContactService(WorkspaceHelper::getCurrentWorkspaceId());
        });

        $this->app->singleton(ChatService::class, function ($app) {
            return new ChatService(
                WorkspaceHelper::getCurrentWorkspaceId(),
                $app->make('App\Services\WhatsApp\MessageService'),
                $app->make('App\Services\WhatsApp\MediaProcessingService'),
                $app->make('App\Services\WhatsApp\TemplateManagementService')
            );
        });

        $this->app->singleton(MediaService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new MediaService($workspace->id);
        });

        // TemplateService moved to BusinessServiceProvider to eliminate duplication

        // SubscriptionService moved to BusinessServiceProvider to eliminate duplication

        // WhatsApp services have been moved to WhatsAppServiceProvider
        // Legacy compatibility maintained through the new provider

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
