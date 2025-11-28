<?php

namespace Backpack\Reviews\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Backpack\Reviews\app\Models\Review;

class ReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
      $hasVideo = $this->faker->boolean(20);
      $lang = $this->faker->randomElement(['en', 'uk', 'ru', 'cs', 'es', 'de']);

      return [
        'is_moderated' => $this->faker->randomElement([1,0]),
        'likes' => $this->faker->randomDigit(),
        'dislikes' => $this->faker->randomDigit(),
        'rating' => $this->faker->numberBetween(1, config('backpack.reviews.rating_length', 5)),
        'text' => $this->faker->paragraph(2),
        'is_video' => $hasVideo,
        'video_url' => $hasVideo ? 'https://www.youtube.com/embed/dQw4w9WgXcQ' : null,
        'video_title' => $hasVideo ? [
          'ru' => $this->faker->sentence(4),
          'en' => $this->faker->sentence(4),
        ] : [],
        'video_poster' => $hasVideo ? [
          [
            'src' => 'reviews/video-posters/example.jpg',
            'alt' => null,
            'title' => null,
            'size' => 'cover',
          ],
        ] : [],
        'extras' => [
          'owner' => [
            'id' => $this->faker->randomDigit(),
            'name' => $this->faker->firstName(),
            'email' => $this->faker->email(),
            'photo' => $this->faker->randomElement([
              'https://images.unsplash.com/photo-1600486913747-55e5470d6f40?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1585807515950-bc46d934c28b?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1559872553-c2607bb6259f?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1495716868937-273203d5bb0c?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D',
              'https://images.unsplash.com/photo-1489985033736-3e81bb38baae?q=80&w=1024&h=1024&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D'
            ]),
          ]
        ],
        'lang' => $lang,
        'country' => strtoupper($this->faker->countryCode()),
      ];
    }

}
