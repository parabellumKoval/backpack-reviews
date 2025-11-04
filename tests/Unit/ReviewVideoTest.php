<?php

namespace Backpack\Reviews\Tests\Unit;

use Backpack\Reviews\app\Models\Review;
use Backpack\Reviews\Tests\TestCase;

class ReviewVideoTest extends TestCase
{
    public function test_video_payload_is_null_when_not_set(): void
    {
        $review = Review::factory()->create([
            'video_url' => null,
            'video_title' => [],
            'video_poster' => [],
            'is_video' => false,
        ]);

        $this->assertNull($review->videoData());
        $this->assertNull($review->videoPosterForApi());
        $this->assertFalse($review->is_video);
    }

    public function test_video_payload_contains_url_title_and_poster(): void
    {
        $review = Review::factory()->create([
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'video_title' => [
                'en' => 'Video review',
                'ru' => 'Видео отзыв',
            ],
            'video_poster' => [
                [
                    'src' => 'reviews/posters/poster.jpg',
                    'alt' => null,
                    'title' => null,
                ],
            ],
            'is_video' => true,
        ]);

        $data = $review->videoData(true);

        $this->assertNotNull($data);
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $data['url']);
        $this->assertSame('Video review', $data['title']);
        $this->assertSame([
            'en' => 'Video review',
            'ru' => 'Видео отзыв',
        ], $data['title_translations']);

        $poster = $data['poster'];
        $expectedUrl = $review->formatImageUrlForAttribute('video_poster', 'reviews/posters/poster.jpg');
        $this->assertSame($expectedUrl, $poster['url']);
        $this->assertSame('reviews/posters/poster.jpg', $poster['path']);
        $this->assertTrue($review->is_video);
    }
}
