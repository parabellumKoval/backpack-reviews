<?php

namespace Backpack\Reviews\database\seeders;

use Backpack\Reviews\app\Models\GoogleReview;
use Backpack\Reviews\app\Models\GoogleReviewConnection;
use Backpack\Reviews\app\Models\GoogleReviewLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput;

class GoogleReviewSeeder extends Seeder
{
    public function run(): void
    {
        $existing = GoogleReview::query()->count();
        if ($existing > 0) {
            (new ConsoleOutput())->writeln('<info>Google reviews already exist. Skipping seed.</info>');
            return;
        }

        $connection = GoogleReviewConnection::query()->first();
        if (!$connection) {
            $connection = GoogleReviewConnection::create([
                'label' => 'Seeded Google Connection',
                'status' => 'active',
                'meta' => ['seeded' => true],
            ]);
        }

        $locations = GoogleReviewLocation::query()
            ->where('connection_id', $connection->id)
            ->get();

        if ($locations->isEmpty()) {
            $locations = collect();
            for ($i = 1; $i <= 3; $i++) {
                $accountId = 'seeded-' . $i;
                $locationId = 'loc-' . $i;
                $locations->push(GoogleReviewLocation::create([
                    'connection_id' => $connection->id,
                    'account_name' => 'accounts/' . $accountId,
                    'account_id' => $accountId,
                    'location_name' => 'accounts/' . $accountId . '/locations/' . $locationId,
                    'location_id' => $locationId,
                    'title' => 'Seeded Location ' . $i,
                    'language_code' => 'cs',
                    'address' => [
                        'locality' => 'Praha',
                        'regionCode' => 'CZ',
                    ],
                    'metadata' => ['seeded' => true],
                    'synced_at' => now(),
                ]));
            }
        }

        $names = [
            'Jan Novak',
            'Eva Svobodova',
            'Pavel Dvorak',
            'Jana Kralova',
            'Martin Prochazka',
            'Petra Vesela',
            'Tomas Kratochvil',
            'Lucie Polakova',
            'Marek Cerny',
            'Klara Fialova',
        ];

        $comments = [
            'Fast delivery and nice packaging.',
            'Good quality, will order again.',
            'Customer support was helpful and quick.',
            'Nice aroma and fresh product.',
            'Great service overall.',
            'Happy with the purchase.',
            'The product met my expectations.',
            'Smooth checkout and fast shipping.',
            'Solid quality for the price.',
            'Everything arrived in perfect condition.',
        ];

        $total = 30;
        for ($i = 1; $i <= $total; $i++) {
            $index = ($i - 1);
            $location = $locations[$index % $locations->count()];
            $reviewId = Str::uuid()->toString();
            $createdAt = Carbon::now()->subDays(random_int(1, 120))->subMinutes(random_int(1, 120));
            $updatedAt = (clone $createdAt)->addDays(random_int(0, 10));
            $reply = $i % 4 === 0 ? 'Thank you for your feedback!' : null;

            GoogleReview::create([
                'location_id' => $location->id,
                'location_name' => $location->location_name,
                'review_name' => rtrim($location->location_name, '/') . '/reviews/' . $reviewId,
                'review_id' => $reviewId,
                'reviewer_name' => $names[$index % count($names)],
                'reviewer_photo_url' => null,
                'reviewer_is_anonymous' => false,
                'rating' => random_int(3, 5),
                'comment' => $comments[$index % count($comments)],
                'review_created_at' => $createdAt,
                'review_updated_at' => $updatedAt,
                'reply_comment' => $reply,
                'reply_updated_at' => $reply ? $updatedAt->addHours(2) : null,
                'metadata' => ['seeded' => true],
                'synced_at' => now(),
            ]);
        }

        (new ConsoleOutput())->writeln('<info>Seeded 30 Google reviews.</info>');
    }
}
