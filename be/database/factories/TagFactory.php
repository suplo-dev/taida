<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Tag $tag): void {
            foreach (config('app.supported_locales') as $locale) {
                $name = fake()->unique()->word();

                $tag->translations()->create([
                    'locale' => $locale,
                    'name' => ucfirst($name),
                    'slug' => str($name)->slug()->toString(),
                ]);
            }
        });
    }
}
