<?php

namespace Backpack\Reviews\app\Traits;

trait Reviewable {
  public function reviews(){
    $model = config('backpack.reviews.review_model', 'Backpack\Reviews\app\Models\Review');
    return $this->morphMany($model, 'reviewable');
  }

  public function getTotalLikesAttribute() {
    return $this->reviews()->moderated()->sum('likes');
  }

  public function getTotalDislikesAttribute() {
    return $this->reviews()->moderated()->sum('dislikes');
  }

  public function getReviewsWithRatingCountAttribute() {
    return $this->reviews()->moderated()->where('rating', '!=', null)->count();
  }

  public function getReviewsCountAttribute() {
    return $this->reviews()->moderated()->count();
  }

  public function getRatingAttribute() {
    $rating = $this->reviews()->moderated()->avg('rating');
    return $rating? round($rating, 1): null;
  }
  
  public function getDetailedRatingAttribute() {
    return $this->reviews()->moderated()->avg('extras.rating.avr');
  }

  public function getSingleDetailedRating($key) {
    if(array_key_exists($key, config('backpack.reviews.detailed_rating_params'))) {
      return $this->reviews()->moderated()->avg('extras.rating.' . $key);
    }else {
      throw new \Exception("Key $key does not exist in allowed array set (config: backpack.reviews.detailed_rating_params). ", 1);
    }
  }

    
  /**
   * getReviewsRatingDetailesAttribute
   *
   * Return model rating data from reviews
   * 
   * @return array => [
   *  int reviews_count, - total reviews for product
   *  int rating_count, - total reviews with rating value
   *  int rating, - total avarage rating of the product form the review's rating
   *  array rating_detailes => [
   *    int rating_1,
   *    int rating_2,
   *    int rating_3,
   *    int rating_4,
   *    int rating_5
   *  ] - how many each rating values (1 stars, 2 stars, etc.)
   * ]
   */
  public function getReviewsRatingDetailesAttribute() {
    $reviews = $this->reviews()->moderated()->get();

    $rating_1 = $reviews->where('rating', 1)->count();
    $rating_2 = $reviews->where('rating', 2)->count();
    $rating_3 = $reviews->where('rating', 3)->count();
    $rating_4 = $reviews->where('rating', 4)->count();
    $rating_5 = $reviews->where('rating', 5)->count();
    
    return [
      'reviews_count' => $reviews->count(),
      'rating_count' => $reviews->where('rating', '!==', null)->count(),
      'rating' => round($this->rating, 1),
      'rating_detailes' => [
        'rating_5' => $rating_5,
        'rating_4' => $rating_4,
        'rating_3' => $rating_3,
        'rating_2' => $rating_2,
        'rating_1' => $rating_1
      ]
    ];
  }
}