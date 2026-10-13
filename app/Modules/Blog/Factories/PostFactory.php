<?php

namespace App\Modules\Blog\Factories;

use App\Modules\Blog\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug(fake()->unique()->sentence(4)),
            'excerpt' => fake()->sentence(12),
            'content' => ['body' => '<p>'.fake()->paragraph().'</p>'],
            'status' => 'draft',
            'published_at' => null,
            'seo_title' => null,
            'seo_description' => null,
        ];
    }

    /**
     * A post that is live on the public site.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now()->subMinutes(1),
        ]);
    }
}
