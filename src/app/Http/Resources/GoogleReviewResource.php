<?php

namespace Backpack\Reviews\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GoogleReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        $location = $this->whenLoaded('location');

        return [
            'id' => $this->id,
            'review_id' => $this->review_id,
            'review_name' => $this->review_name,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reviewer' => [
                'name' => $this->reviewer_name,
                'photo_url' => $this->reviewer_photo_url,
                'photo_path' => $this->reviewer_photo_path,
                'is_anonymous' => (bool) $this->reviewer_is_anonymous,
            ],
            'reply' => $this->reply_comment ? [
                'comment' => $this->reply_comment,
                'updated_at' => $this->reply_updated_at,
            ] : null,
            'location' => $location ? [
                'id' => $location->id,
                'title' => $location->title,
                'account_id' => $location->account_id,
                'location_name' => $location->location_name,
            ] : null,
            'review_created_at' => $this->review_created_at,
            'review_updated_at' => $this->review_updated_at,
        ];
    }
}
