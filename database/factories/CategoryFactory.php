<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'title' => $title,
            'slug' => Str::slug($title, '-', 'tr'),
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->getKey()]);
    }
}
