<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'icon' => fake()->randomElement(['shield-check', 'flask-conical', 'search', 'badge-check']),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_featured' => false,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 200)),
        ];
    }

    /**
     * Every service is created with a full set of translations so tests and
     * seeds never hit a record the public API would have to skip.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Service $service): void {
            foreach (config('app.supported_locales') as $locale) {
                $name = fake()->unique()->words(3, true);

                $service->translations()->create([
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

    /** Scheduled for the future, so it must stay hidden from the public API. */
    public function scheduled(): static
    {
        return $this->state([
            'status' => ContentStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }
}
