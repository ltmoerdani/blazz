<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Template>
 */
class TemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Template::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'uuid' => $this->faker->uuid,
            'workspace_id' => Workspace::factory(),
            'category' => 'UTILITY',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'header_type' => 'TEXT',
            'header_text' => $this->faker->sentence(3),
            'header_media' => null,
            'body_text' => $this->faker->paragraph(2),
            'footer_text' => $this->faker->sentence(1),
            'buttons_data' => null,
            'metadata' => json_encode([]),
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the template is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PENDING',
        ]);
    }

    /**
     * Indicate that the template is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'REJECTED',
        ]);
    }

    /**
     * Create a template with an image header.
     */
    public function withImageHeader(): static
    {
        return $this->state(fn (array $attributes) => [
            'header_type' => 'IMAGE',
            'header_media' => 'test-image.jpg',
        ]);
    }

    /**
     * Create a template with buttons.
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
}