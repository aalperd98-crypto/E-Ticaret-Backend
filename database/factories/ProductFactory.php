<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title, '-', 'tr'),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 5000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'thumbnail_path' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
