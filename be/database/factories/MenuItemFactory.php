<?php

namespace Database\Factories;

use App\Enums\MenuLocation;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'location' => MenuLocation::Header,
            'parent_id' => null,
            'sort_order' => fake()->numberBetween(0, 10),
            'opens_in_new_tab' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MenuItem $item): void {
            foreach (config('app.supported_locales') as $locale) {
                $label = fake()->unique()->words(2, true);

                $item->translations()->create([
                    'locale' => $locale,
                    'label' => ucfirst($label),
                    'url' => '/'.str($label)->slug(),
                ]);
            }
        });
    }

    public function footer(): static
    {
        return $this->state(['location' => MenuLocation::Footer]);
    }

    public function utility(): static
    {
        return $this->state(['location' => MenuLocation::Utility]);
    }
}
