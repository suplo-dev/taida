<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(3);

        return [
            'disk' => 'public',
            'path' => "media/{$name}.jpg",
            'thumb_path' => "media/thumbs/{$name}.jpg",
            'original_name' => "{$name}.jpg",
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'width' => 1600,
            'height' => 900,
            'alt' => ['vi' => fake()->sentence(4), 'en' => fake()->sentence(4)],
        ];
    }
}
