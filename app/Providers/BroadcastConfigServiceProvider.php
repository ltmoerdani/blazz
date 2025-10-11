<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;

class BroadcastConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        if (! env('ENABLE_DATABASE_CONFIG', false)) {
            return;
        }

        $settings = $this->getBroadcastSettings();

        if (! empty($settings['broadcast_driver'])) {
            Config::set('broadcasting.default', $settings['broadcast_driver']);
        }

        // Pusher settings (if available)
        Config::set('broadcasting.connections.pusher.key', $settings['pusher_app_key'] ?? Config::get('broadcasting.connections.pusher.key'));
        Config::set('broadcasting.connections.pusher.secret', $settings['pusher_app_secret'] ?? Config::get('broadcasting.connections.pusher.secret'));
        Config::set('broadcasting.connections.pusher.app_id', $settings['pusher_app_id'] ?? Config::get('broadcasting.connections.pusher.app_id'));
        if (! empty($settings['pusher_app_cluster'])) {
            Config::set('broadcasting.connections.pusher.options.cluster', $settings['pusher_app_cluster']);
        }

        // Reverb settings (uses Pusher protocol options)
        Config::set('broadcasting.connections.reverb.key', $settings['reverb_app_key'] ?? Config::get('broadcasting.connections.reverb.key'));
        Config::set('broadcasting.connections.reverb.secret', $settings['reverb_app_secret'] ?? Config::get('broadcasting.connections.reverb.secret'));
        Config::set('broadcasting.connections.reverb.app_id', $settings['reverb_app_id'] ?? Config::get('broadcasting.connections.reverb.app_id'));
        if (! empty($settings['reverb_host'])) {
            Config::set('broadcasting.connections.reverb.options.host', $settings['reverb_host']);
        }
        if (! empty($settings['reverb_port'])) {
            Config::set('broadcasting.connections.reverb.options.port', (int) $settings['reverb_port']);
        }
        if (! empty($settings['reverb_scheme'])) {
            Config::set('broadcasting.connections.reverb.options.scheme', $settings['reverb_scheme']);
            Config::set('broadcasting.connections.reverb.options.useTLS', $settings['reverb_scheme'] === 'https');
        }
    }

    /**
     * Fetch Pusher settings from the database.
     *
     * @return array
     */
    private function getBroadcastSettings()
    {
        if (! env('ENABLE_DATABASE_CONFIG', false)) {
            return [];
        }

        return Setting::whereIn('key', [
            // Driver selector
            'broadcast_driver',

            // Pusher keys
            'pusher_app_key',
            'pusher_app_secret',
            'pusher_app_id',
            'pusher_app_cluster',

            // Reverb keys
            'reverb_app_id',
            'reverb_app_key',
            'reverb_app_secret',
            'reverb_host',
            'reverb_port',
            'reverb_scheme',
        ])->pluck('value', 'key')->toArray();
    }
}
