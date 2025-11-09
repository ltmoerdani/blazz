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

        // WhatsApp Service Registration
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
