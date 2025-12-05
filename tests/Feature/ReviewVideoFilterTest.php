<?php

namespace Backpack\Reviews\Tests\Feature;

use Backpack\Reviews\app\Models\Review;
use Backpack\Reviews\Tests\TestCase;

class ReviewVideoFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Review::query()->delete();
    }

    public function test_index_excludes_video_reviews_by_default(): void
    {
        $textReview = Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'video_url' => null,
            'parent_id' => 0,
        ]);

        Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => true,
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'parent_id' => 0,
        ]);

        $response = $this->getJson('/api/review');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($textReview->id, $data[0]['id']);
        $this->assertFalse($data[0]['is_video']);
    }

    public function test_index_returns_only_videos_when_requested(): void
    {
        Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'video_url' => null,
            'parent_id' => 0,
        ]);

        $video = Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => true,
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'parent_id' => 0,
        ]);

        $response = $this->getJson('/api/review?video=1');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($video->id, $data[0]['id']);
        $this->assertTrue($data[0]['is_video']);
    }

    public function test_index_relation_respects_video_filter(): void
    {
        Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'video_url' => null,
            'parent_id' => 0,
        ]);

        $video = Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => true,
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'parent_id' => 0,
        ]);

        $response = $this->getJson('/api/review/relation?video=1');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($video->id, $data[0]['id']);
        $this->assertTrue($data[0]['is_video']);
    }

    public function test_index_relation_allows_null_reviewable_type_filter(): void
    {
        $shopReview = Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'reviewable_type' => null,
            'reviewable_id' => null,
            'parent_id' => 0,
        ]);

        Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'reviewable_type' => 'App\\Models\\Product',
            'reviewable_id' => 42,
            'parent_id' => 0,
        ]);

        $response = $this->getJson('/api/review/relation?reviewable_type_null=1');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($shopReview->id, $data[0]['id']);
        $this->assertNull($data[0]['reviewable_type']);
    }

    public function test_index_allows_null_reviewable_type_filter(): void
    {
        $shopReview = Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'reviewable_type' => null,
            'reviewable_id' => null,
            'parent_id' => 0,
        ]);

        Review::factory()->create([
            'is_moderated' => 1,
            'is_video' => false,
            'reviewable_type' => 'App\\Models\\Product',
            'reviewable_id' => 42,
            'parent_id' => 0,
        ]);

        $response = $this->getJson('/api/review?reviewable_type_null=1');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($shopReview->id, $data[0]['id']);
        $this->assertNull($data[0]['reviewable_type']);
    }
}
