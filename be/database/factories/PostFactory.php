<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'category_id' => null,
            'author_id' => null,
            'is_featured' => false,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 300)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Post $post): void {
            foreach (config('app.supported_locales') as $locale) {
                $title = fake()->unique()->sentence(6);

                $post->translations()->create([
                    'locale' => $locale,
                    'title' => $title,
                    'slug' => str($title)->slug()->toString(),
                    'excerpt' => fake()->sentence(18),
                    'body' => '<p>'.fake()->paragraphs(5, true).'</p>',
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

    public function scheduled(): static
    {
        return $this->state([
            'status' => ContentStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }
}
