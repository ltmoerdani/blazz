<?php

namespace Database\Factories;

use App\Models\ContactGroup;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContactGroup>
 */
class ContactGroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ContactGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'uuid' => $this->faker->uuid,
            'workspace_id' => Workspace::factory(),
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}