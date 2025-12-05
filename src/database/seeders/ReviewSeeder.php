<?php

namespace Backpack\Reviews\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Reviews\app\Models\Review;

class ReviewSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      Review::where('id', '>=', 0)->delete();
      (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln("<info>Review was deleted.</info>");

      $this->fill();
      $this->seedVivadzenReviews();
    }

    public function fill() {

      $users = null;
      $reviewable_list = null;

      $OWNER_MODEL = config('backpack.reviews.owner_model', null);
      $users = $OWNER_MODEL? $OWNER_MODEL::inRandomOrder()->limit(5)->get(): null;
      
      $REVIEWABLE_MODEL = config('backpack.reviews.reviewable_model', null);
      $reviewable_list = $REVIEWABLE_MODEL? $REVIEWABLE_MODEL::inRandomOrder()->limit(30)->get(): null;

      if($reviewable_list) {
        foreach($reviewable_list as $reviewable) {
          $random_user = $users? $users->random(): null;
          $random_user_id = $random_user? $random_user->id: null; 
          $this->create(10, 3, $random_user_id, $reviewable->id, $REVIEWABLE_MODEL);
        }
      }elseif($users) {
        foreach($users as $user) {
          $this->create(10, 3, $user->id);
        }
      }else {
        $this->create(10, 3, null);
      }
    }

    public function create(int $amount = 10, int $children = 3, int $owner_id = null, int $reviewable_id = null, string $reviewable_type = null) {
      Review::factory()
        ->count($amount)
        ->hasChildren(3)
        ->state([
          'owner_id' => $owner_id,
          'reviewable_id' => $reviewable_id,
          'reviewable_type' => $reviewable_type,
        ])
        ->create();
    }

    protected function seedVivadzenReviews(): void
    {
      $dataPath = __DIR__ . '/data/vivadzen_reviews.php';

      if (!file_exists($dataPath)) {
        return;
      }

      $reviews = include $dataPath;

      if (!is_array($reviews) || empty($reviews)) {
        return;
      }

      $defaults = [
        'owner_id' => null,
        'parent_id' => null,
        'reviewable_id' => null,
        'reviewable_type' => null,
        'video_url' => null,
        'video_title' => [],
        'video_poster' => [],
        'is_video' => false,
        'is_moderated' => true,
      ];

      $inserted = 0;

      foreach ($reviews as $review) {
        if (!is_array($review) || empty($review['text'])) {
          continue;
        }

        Review::create(array_merge($defaults, $review));
        $inserted++;
      }

      if ($inserted > 0) {
        (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln('<info>Seeded ' . $inserted . ' Vivadzen showcase reviews.</info>');
      }
    }
}
