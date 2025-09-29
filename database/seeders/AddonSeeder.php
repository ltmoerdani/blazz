<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Addon;

class AddonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $addons = [
            [
                'category' => 'authentication',
                'name' => 'Google Authenticator',
                'logo' => 'google-auth.png',
                'description' => 'Two-factor authentication using Google Authenticator',
                'metadata' => json_encode(['type' => '2fa']),
                'status' => 1,
                'is_active' => 1,
                'version' => '1.0.0',
                'update_available' => 0,
            ],
            // Add more addons here if needed
        ];

        foreach ($addons as $addon) {
            Addon::firstOrCreate(
                ['name' => $addon['name']],
                $addon
            );
        }
    }
}