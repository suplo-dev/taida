<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Industry>
 */
class IndustryFactory extends Factory
{
    protected $model = Industry::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'icon' => fake()->randomElement(['factory', 'hard-hat', 'fuel', 'utensils']),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_featured' => false,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 200)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Industry $industry): void {
            foreach (config('app.supported_locales') as $locale) {
                $name = fake()->unique()->words(2, true);

                $industry->translations()->create([
                    'locale' => $locale,
                    'name' => ucfirst($name),
                    'slug' => str($name)->slug()->toString(),
                    'excerpt' => fake()->sentence(14),
                    'body' => '<p>'.fake()->paragraphs(3, true).'</p>',
                ]);
            }
        });
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
