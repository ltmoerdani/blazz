<?php

namespace App\Providers;

use App\Helpers\WorkspaceHelper;
use App\Services\WhatsappService;
use App\Services\WhatsApp\MessageSendingService;
use App\Services\WhatsApp\TemplateManagementService;
use App\Services\WhatsApp\MediaProcessingService;
use App\Services\WhatsApp\BusinessProfileService;
use App\Services\WhatsApp\WhatsAppHealthService;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    /**
     * Workspace resolution moved to WorkspaceHelper to eliminate duplication
     */

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Legacy WhatsApp Service (maintains backward compatibility)
        $this->app->singleton(WhatsappService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new WhatsappService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        // Modern WhatsApp Services
        $this->app->singleton(MessageSendingService::class, function ($app) {
            $workspace = WorkspaceHelper::getCurrentWorkspace();
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
            $workspace = WorkspaceHelper::getCurrentWorkspace();
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
            $workspace = WorkspaceHelper::getCurrentWorkspace();
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
            $workspace = WorkspaceHelper::getCurrentWorkspace();
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
            $workspace = WorkspaceHelper::getCurrentWorkspace();
            return new WhatsAppHealthService(
                $workspace->meta_token,
                $workspace->meta_version,
                $workspace->meta_app_id,
                $workspace->meta_phone_number_id,
                $workspace->meta_waba_id,
                $workspace->id
            );
        });

        // WhatsApp Session Service
        $this->app->singleton(\App\Services\WhatsApp\WhatsAppAccountService::class, function ($app) {
            return new \App\Services\WhatsApp\WhatsAppAccountService(
                $app->make(\App\Services\ProviderSelector::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This provider is specifically for WhatsApp-related services
        // Additional WhatsApp-specific boot logic can be added here
    }
}