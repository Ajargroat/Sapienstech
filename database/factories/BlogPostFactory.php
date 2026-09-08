<?php

namespace Database\Factories;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = 'نوشته آزمایشی '.Str::upper(Str::random(6));

        return [
            'title' => $title,
            // Slug is set explicitly (not auto-derived) so the factory never
            // runs the global-uniqueness loop against a shared test database.
            'slug' => 'test-'.Str::lower(Str::random(10)),
            'excerpt' => $this->faker->sentence(),
            'body' => $this->faker->paragraphs(3, true),
            'status' => BlogPost::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
