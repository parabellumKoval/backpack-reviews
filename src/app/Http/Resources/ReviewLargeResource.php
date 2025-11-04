<?php

namespace Backpack\Reviews\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewLargeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      return [
        'id' => $this->id,
        'rating' => $this->rating,
        'likes' => $this->likes? $this->likes: 0,
        'dislikes' => $this->dislikes? $this->dislikes: 0,
        'text' => $this->text,
        'owner' => $this->ownerModelOrInfo,
        'video' => $this->videoData(true),
        'is_video' => (bool) $this->is_video,
        'extras' => $this->extras,
        'children' => self::collection($this->children),
        'reviewable' => $this->shortReviewable,
        'created_at' => $this->created_at
      ];
    }
}
