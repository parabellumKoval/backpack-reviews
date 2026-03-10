<?php

namespace Backpack\Reviews\app\Services;

use Backpack\Reviews\app\Models\GoogleReview;
use Backpack\Reviews\app\Models\GoogleReviewConnection;
use Backpack\Reviews\app\Models\GoogleReviewLocation;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GoogleReviewsSyncService
{
    public function __construct(
        protected GoogleBusinessProfileClient $client,
        protected GoogleReviewAvatarStorage $avatarStorage
    ) {
    }

    public function syncAll(): array
    {
        $results = [
            'connections' => 0,
            'locations' => 0,
            'reviews_created' => 0,
            'reviews_updated' => 0,
        ];

        $connections = GoogleReviewConnection::query()
            ->where('status', 'active')
            ->get();

        foreach ($connections as $connection) {
            $connectionResult = $this->syncConnection($connection);
            $results['connections']++;
            $results['locations'] += $connectionResult['locations'];
            $results['reviews_created'] += $connectionResult['reviews_created'];
            $results['reviews_updated'] += $connectionResult['reviews_updated'];
        }

        return $results;
    }

    public function syncConnection(GoogleReviewConnection $connection): array
    {
        $accessToken = $this->client->ensureAccessToken($connection);
        $accounts = $this->client->listAccounts($accessToken);

        $stats = [
            'locations' => 0,
            'reviews_created' => 0,
            'reviews_updated' => 0,
        ];

        foreach ($accounts as $account) {
            $accountName = Arr::get($account, 'name');
            if (!$accountName) {
                continue;
            }

            $locations = $this->client->listLocations($accessToken, $accountName);
            foreach ($locations as $locationPayload) {
                $location = $this->upsertLocation($connection, $accountName, $locationPayload);
                if (!$location) {
                    continue;
                }
                $stats['locations']++;
                $reviewStats = $this->syncLocationReviews($accessToken, $location);
                $stats['reviews_created'] += $reviewStats['created'];
                $stats['reviews_updated'] += $reviewStats['updated'];
            }
        }

        return $stats;
    }

    protected function upsertLocation(
        GoogleReviewConnection $connection,
        string $accountName,
        array $locationPayload
    ): ?GoogleReviewLocation {
        $locationName = Arr::get($locationPayload, 'name');
        if (!$locationName) {
            return null;
        }

        $location = GoogleReviewLocation::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'location_name' => $locationName,
            ],
            [
                'account_name' => $accountName,
                'account_id' => $this->client->normalizeAccountId($accountName),
                'location_id' => $this->client->normalizeLocationId($locationName),
                'title' => Arr::get($locationPayload, 'title'),
                'store_code' => Arr::get($locationPayload, 'storeCode'),
                'language_code' => Arr::get($locationPayload, 'languageCode'),
                'address' => Arr::get($locationPayload, 'storefrontAddress'),
                'metadata' => $locationPayload,
                'synced_at' => now(),
            ]
        );

        return $location;
    }

    protected function syncLocationReviews(string $accessToken, GoogleReviewLocation $location): array
    {
        $created = 0;
        $updated = 0;
        $pageToken = null;

        do {
            $payload = $this->client->listReviews(
                $accessToken,
                $location->location_name,
                $pageToken,
                $location->account_name
            );
            $reviews = $payload['reviews'] ?? [];
            foreach ($reviews as $reviewPayload) {
                $result = $this->upsertReview($location, $reviewPayload);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            }

            $pageToken = $payload['nextPageToken'] ?? null;
        } while ($pageToken);

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    protected function upsertReview(GoogleReviewLocation $location, array $reviewPayload): string
    {
        $reviewName = Arr::get($reviewPayload, 'name');
        $reviewId = Arr::get($reviewPayload, 'reviewId');

        if (!$reviewName && $reviewId) {
            $reviewName = rtrim($location->location_name, '/') . '/reviews/' . $reviewId;
        }

        if (!$reviewName) {
            return 'skipped';
        }

        $rating = $this->normalizeRating(Arr::get($reviewPayload, 'starRating'));
        $reviewer = Arr::get($reviewPayload, 'reviewer', []);
        $reply = Arr::get($reviewPayload, 'reviewReply', []);

        $attributes = [
            'location_id' => $location->id,
            'location_name' => $location->location_name,
            'review_id' => $reviewId ?: Str::afterLast($reviewName, '/'),
            'reviewer_name' => Arr::get($reviewer, 'displayName'),
            'reviewer_is_anonymous' => (bool) Arr::get($reviewer, 'isAnonymous', false),
            'rating' => $rating,
            'comment' => Arr::get($reviewPayload, 'comment'),
            'review_created_at' => $this->parseTimestamp(Arr::get($reviewPayload, 'createTime')),
            'review_updated_at' => $this->parseTimestamp(Arr::get($reviewPayload, 'updateTime')),
            'reply_comment' => Arr::get($reply, 'comment'),
            'reply_updated_at' => $this->parseTimestamp(Arr::get($reply, 'updateTime')),
            'metadata' => $reviewPayload,
            'synced_at' => now(),
        ];

        $avatarPayload = $this->avatarStorage->storeFromSource(
            Arr::get($reviewer, 'profilePhotoUrl'),
            (string) ($reviewId ?: $reviewName)
        );
        if ($avatarPayload) {
            $attributes['reviewer_photo_url'] = $avatarPayload['url'];
            $attributes['reviewer_photo_path'] = $avatarPayload['path'];
        }

        $review = GoogleReview::query()->where('review_name', $reviewName)->first();
        if (!$review) {
            GoogleReview::create([
                'review_name' => $reviewName,
                'is_active' => true,
                'sort_order' => 0,
            ] + $attributes);
            return 'created';
        }

        $review->fill($attributes);
        if ($review->isDirty()) {
            $review->save();
            return 'updated';
        }

        return 'unchanged';
    }

    protected function normalizeRating(?string $rating): ?int
    {
        if (!$rating) {
            return null;
        }

        if (is_numeric($rating)) {
            return (int) $rating;
        }

        return match (strtoupper($rating)) {
            'ONE' => 1,
            'TWO' => 2,
            'THREE' => 3,
            'FOUR' => 4,
            'FIVE' => 5,
            default => null,
        };
    }

    protected function parseTimestamp(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
