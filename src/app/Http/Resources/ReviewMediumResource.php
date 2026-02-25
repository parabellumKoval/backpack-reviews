<?php

namespace Backpack\Reviews\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReviewMediumResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

      $clear_extras = $this->extras;
      unset($clear_extras['owner']);
      unset($clear_extras['video']);
      
      return [
        'id' => $this->id,
        'rating' => $this->rating,
        'likes' => $this->likes? $this->likes: 0,
        'dislikes' => $this->dislikes? $this->dislikes: 0,
        'text' => $this->text,
        'owner' => $this->ownerModelOrInfo,
        'video' => $this->videoData(true),
        'is_video' => (bool) $this->is_video,
        'review_type' => $this->resolveReviewType(),
        'photos' => $this->photoGalleryForApi(),
        'extras' => $clear_extras,
        'children' => self::collection($this->children),
        'lang' => $this->lang,
        'country' => $this->country,
        'created_at' => $this->created_at
      ];
    }
}
