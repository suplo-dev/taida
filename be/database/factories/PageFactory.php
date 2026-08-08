<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'status' => ContentStatus::Published,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Page $page): void {
            foreach (config('app.supported_locales') as $locale) {
                $title = fake()->unique()->words(3, true);

                $page->translations()->create([
                    'locale' => $locale,
                    'title' => ucfirst($title),
                    'slug' => str($title)->slug()->toString(),
                    'body' => '<p>'.fake()->paragraphs(4, true).'</p>',
                ]);
            }
        });
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft]);
    }
}
