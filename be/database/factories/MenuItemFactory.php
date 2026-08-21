<?php

namespace Database\Factories;

use App\Enums\MenuLocation;
use App\Enums\MenuTarget;
use App\Enums\SiteRoute;
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
            'target_type' => MenuTarget::Route,
            'target_route' => fake()->randomElement(SiteRoute::cases()),
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

    /** Trỏ tới một bản ghi thật thay vì một trang danh sách. */
    public function pointingAt(MenuTarget $type, int $id): static
    {
        return $this->state([
            'target_type' => $type,
            'target_route' => null,
            'target_id' => $id,
        ]);
    }

    /** Mục chưa chọn đích: site bỏ qua, admin đánh dấu để sửa. */
    public function withoutTarget(): static
    {
        return $this->state(['target_type' => MenuTarget::Route, 'target_route' => null]);
    }
}
