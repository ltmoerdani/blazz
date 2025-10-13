<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Setting;

class LaravelReverbSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds for Laravel Reverb configuration
     *
     * @return void
     */
    public function run()
    {
        $reverbSettings = [
            [
                'key' => 'broadcast_driver',
                'value' => env('BROADCAST_DRIVER', 'reverb'),
            ],
            [
                'key' => 'reverb_app_id',
                'value' => env('REVERB_APP_ID', 'default-app-id'),
            ],
            [
                'key' => 'reverb_app_key',
                'value' => env('REVERB_APP_KEY', 'base64:' . base64_encode(Str::random(32))),
            ],
            [
                'key' => 'reverb_app_secret',
                'value' => env('REVERB_APP_SECRET', 'base64:' . base64_encode(Str::random(32))),
            ],
            [
                'key' => 'reverb_host',
                'value' => env('REVERB_HOST', '127.0.0.1'),
            ],
            [
                'key' => 'reverb_port',
                'value' => env('REVERB_PORT', '8080'),
            ],
            [
                'key' => 'reverb_scheme',
                'value' => env('REVERB_SCHEME', 'http'),
            ],
        ];

        foreach ($reverbSettings as $setting) {
            // Check if setting already exists (idempotency)
            $exists = Setting::where('key', $setting['key'])->exists();

            if (!$exists) {
                Setting::create($setting);
                $this->command->info("✓ Created setting: {$setting['key']}");
            } else {
                $this->command->info("⊙ Setting already exists: {$setting['key']}");
            }
        }

        $this->command->info('Laravel Reverb settings seeded successfully!');
    }
}
