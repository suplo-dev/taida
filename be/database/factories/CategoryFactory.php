<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return ['sort_order' => fake()->numberBetween(0, 10)];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Category $category): void {
            foreach (config('app.supported_locales') as $locale) {
                $name = fake()->unique()->words(2, true);

                $category->translations()->create([
                    'locale' => $locale,
                    'name' => ucfirst($name),
                    'slug' => str($name)->slug()->toString(),
                    'description' => fake()->sentence(12),
                ]);
            }
        });
    }
}
