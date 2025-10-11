<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Team;
use App\Models\Addon;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or Update Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Demo',
                'password' => Hash::make('password12345678'),
                'role' => 'admin',
                'status' => 1,
                'phone' => '+6281234567890',
                'address' => 'Jakarta, Indonesia',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✅ Admin: {$admin->email} " . ($admin->wasRecentlyCreated ? '(created)' : '(updated)'));

        // Create or Update Regular User (Laksmana)
        $user = User::updateOrCreate(
            ['email' => 'ltmoerdani@yahoo.com'],
            [
                'first_name' => 'Laksmana',
                'last_name' => 'Moerdani',
                'password' => Hash::make('Bismillah042899'),
                'role' => 'user',
                'status' => 1,
                'phone' => '+6281234567891',
                'address' => 'Indonesia',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("✅ User: {$user->email} " . ($user->wasRecentlyCreated ? '(created)' : '(updated)'));

        // Create or Update Workspace for User
        $workspace = Workspace::updateOrCreate(
            ['identifier' => 'laksmana-workspace'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Laksmana Workspace',
                'address' => 'Indonesia',
                'timezone' => 'Asia/Jakarta',
                'metadata' => json_encode([
                    'created_via' => 'seeder',
                    'environment' => 'testing',
                ]),
                'created_by' => $user->id,
            ]
        );

        $this->command->info("✅ Workspace: {$workspace->name} " . ($workspace->wasRecentlyCreated ? '(created)' : '(updated)'));

        // Create Team relationship (User as Owner of Workspace) - only if not exists
        $team = Team::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'role' => 'owner',
                'status' => 'active',
                'created_by' => $user->id,
            ]
        );

        $this->command->info("✅ Team relationship " . ($team->wasRecentlyCreated ? '(created)' : '(exists)'));

        // Create Default Subscription Plan (Free Trial)
        $plan = SubscriptionPlan::firstOrCreate(
            ['name' => 'Free Trial'],
            [
                'uuid' => (string) Str::uuid(),
                'price' => 0.00,
                'period' => 'monthly',
                'metadata' => json_encode([
                    'features' => ['Basic Features', 'Limited Messages', '1 WhatsApp Session'],
                    'limits' => [
                        'messages_per_month' => 1000,
                        'contacts' => 500,
                        'campaigns' => 10,
                    ],
                ]),
                'status' => 'active',
            ]
        );

        $this->command->info("✅ Subscription Plan: {$plan->name} " . ($plan->wasRecentlyCreated ? '(created)' : '(exists)'));

        // Create Subscription for Workspace
        $subscription = Subscription::firstOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'uuid' => (string) Str::uuid(),
                'plan_id' => $plan->id,
                'start_date' => now(),
                'valid_until' => now()->addDays(30), // 30 days trial
                'status' => 'trial',
            ]
        );

        $this->command->info("✅ Subscription " . ($subscription->wasRecentlyCreated ? '(created)' : '(exists)'));

        // Create Google Recaptcha Addon (to prevent LoginRequest error) - only if not exists
        $addon = Addon::firstOrCreate(
            ['name' => 'Google Recaptcha'],
            [
                'uuid' => (string) Str::uuid(),
                'category' => 'Security',
                'logo' => 'recaptcha.png',
                'description' => 'Google reCAPTCHA v2 integration',
                'status' => 0, // Disabled by default
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info("✅ Google Recaptcha addon " . ($addon->wasRecentlyCreated ? '(created)' : '(exists)'));

        $separator = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        
        $this->command->info("\n🎉 Demo users seeding completed!");
        $this->command->info($separator);
        $this->command->info("📧 Admin: admin@demo.com");
        $this->command->info("🔑 Password: password12345678");
        $this->command->info($separator);
        $this->command->info("📧 User: ltmoerdani@yahoo.com");
        $this->command->info("🔑 Password: Bismillah042899");
        $this->command->info("👤 Name: Laksmana Moerdani");
        $this->command->info("🏢 Workspace: Laksmana Workspace");
        $this->command->info($separator);
    }
}
