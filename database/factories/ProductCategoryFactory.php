<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\ProductCategory;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name'        => Str::title($name),
            'slug'        => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(),
            'status'      => Status::ACTIVE,
            'parent_id'   => null,
        ];
    }

    /**
     * Attach the category as a child of the given parent.
     */
    public function childOf(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Mark the category as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::INACTIVE,
        ]);
    }
}
