<?php

declare(strict_types=1);

namespace App\Modules\Media\Database\Factories;

use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Radif-e rasane bedoone file-e vaghei rooye disk — baraye test-hayi ke faghat rabete mikhan.
 *
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    protected $model = Media::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $ulid = strtolower($this->faker->unique()->lexify('??????????'));

        return [
            'disk' => 'public',
            'path' => "media/2026/09/{$ulid}.png",
            'name' => 'sample.png',
            'mime' => 'image/png',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
            'alt' => $this->faker->sentence(3),
            'title' => null,
            'derivatives' => [
                'webp' => "media/2026/09/{$ulid}.webp",
                'thumb' => "media/2026/09/{$ulid}-400.webp",
                'srcset' => [['w' => 400, 'path' => "media/2026/09/{$ulid}-400.webp"]],
            ],
            'uploaded_by' => null,
        ];
    }

    public function svg(): static
    {
        return $this->state(fn (array $attributes): array => [
            'path' => str_replace('.png', '.svg', (string) $attributes['path']),
            'name' => 'sample.svg',
            'mime' => 'image/svg+xml',
            'derivatives' => null,
        ]);
    }
}
