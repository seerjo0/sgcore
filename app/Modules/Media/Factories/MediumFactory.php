<?php

namespace App\Modules\Media\Factories;

use App\Modules\Media\Models\Medium;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Medium>
 */
class MediumFactory extends Factory
{
    /**
     * The model this factory creates.
     *
     * @var class-string<Medium>
     */
    protected $model = Medium::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filename' => fake()->word().'.jpg',
            'path' => now()->format('Y').'/'.now()->format('m').'/'.Str::random(40).'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'width' => fake()->randomElement([800, 1200, 1920]),
            'height' => fake()->randomElement([600, 800, 1080]),
            'alt' => null,
            'caption' => null,
        ];
    }

    /**
     * Indicate that the file is a PNG.
     */
    public function png(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/png',
            'filename' => 'imagem.png',
            'path' => now()->format('Y').'/'.now()->format('m').'/'.Str::random(40).'.png',
        ]);
    }
}
