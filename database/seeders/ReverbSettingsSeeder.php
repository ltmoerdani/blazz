<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ReverbSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reverbSettings = [
            [
                'key' => 'broadcast_driver',
                'value' => config('broadcasting.default', 'reverb'),
            ],
            [
                'key' => 'reverb_app_id',
                'value' => config('reverb.apps.apps.0.app_id', '526180'),
            ],
            [
                'key' => 'reverb_app_key',
                'value' => config('reverb.apps.apps.0.key', 'ohrtagckj2hqoiocg7wz'),
            ],
            [
                'key' => 'reverb_app_secret',
                'value' => config('reverb.apps.apps.0.secret', 'ag0aapako3p6n90f6etl'),
            ],
            [
                'key' => 'reverb_host',
                'value' => config('reverb.apps.apps.0.options.host', '127.0.0.1'),
            ],
            [
                'key' => 'reverb_port',
                'value' => config('reverb.apps.apps.0.options.port', '8080'),
            ],
            [
                'key' => 'reverb_scheme',
                'value' => config('reverb.apps.apps.0.options.scheme', 'http'),
            ],
        ];

        foreach ($reverbSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('✅ Reverb settings seeded successfully!');
    }
}
