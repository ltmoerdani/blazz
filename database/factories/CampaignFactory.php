<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Workspace;
use App\Models\Template;
use App\Models\ContactGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Campaign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid,
            'workspace_id' => Workspace::factory(),
            'template_id' => null,
            'contact_group_id' => ContactGroup::factory(),
            'name' => $this->faker->words(3, true),
            'status' => 'pending',
            'campaign_type' => 'direct',
            'preferred_provider' => 'webjs',
            'whatsapp_account_id' => null,
            'scheduled_at' => null,
            'body_text' => $this->faker->paragraph(2),
            'header_type' => 'text',
            'header_text' => $this->faker->sentence(3),
            'header_media' => null,
            'footer_text' => $this->faker->sentence(1),
            'buttons_data' => null,
            'metadata' => json_encode([]),
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Create a template-based campaign.
     */
    public function templateBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_type' => 'template',
            'template_id' => Template::factory(),
        ]);
    }

    /**
     * Create a direct message campaign.
     */
    public function directMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_type' => 'direct',
        ]);
    }

    /**
     * Create a scheduled campaign.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(2),
        ]);
    }

    /**
     * Create an ongoing campaign.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ongoing',
        ]);
    }

    /**
     * Create a completed campaign.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Create a failed campaign.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Create a campaign with buttons.
     */
    public function withButtons(): static
    {
        return $this->state(fn (array $attributes) => [
            'buttons_data' => [
                [
                    'type' => 'reply',
                    'text' => 'Option 1'
                ],
                [
                    'type' => 'reply',
                    'text' => 'Option 2'
                ]
            ]
        ]);
    }

    /**
     * Create a campaign with Meta API as preferred provider.
     */
    public function withMetaApi(): static
    {
        return $this->state(fn (array $attributes) => [
            'preferred_provider' => 'meta_api',
        ]);
    }
}