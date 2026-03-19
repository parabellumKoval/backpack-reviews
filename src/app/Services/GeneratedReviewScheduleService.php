<?php

namespace Backpack\Reviews\app\Services;

use Backpack\Reviews\app\Models\Review;
use Backpack\Schedule\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GeneratedReviewScheduleService
{
    public function __construct(private readonly ScheduleService $scheduleService)
    {
    }

    public function schedule(
        Collection $reviews,
        Carbon $startAt,
        int $minPerDay,
        int $maxPerDay,
        int $hourFrom,
        int $hourTo
    ): int {
        $reviews = $reviews
            ->filter(fn ($review) => $review instanceof Review && $review->exists)
            ->values();

        if ($reviews->isEmpty()) {
            return 0;
        }

        $reviews = $this->buildPublicationQueue($reviews);

        $plans = $this->buildDailyPlans($reviews->count(), $minPerDay, $maxPerDay);
        $scheduled = 0;
        $cursor = 0;
        $dayCursor = $startAt->copy();
        $firstDay = true;

        foreach ($plans as $count) {
            $times = $this->buildPublishTimesForDay(
                $dayCursor->copy()->startOfDay(),
                $count,
                $hourFrom,
                $hourTo,
                $firstDay ? $startAt->copy() : null
            );

            if ($times === []) {
                $dayCursor = $dayCursor->copy()->addDay()->startOfDay();
                $times = $this->buildPublishTimesForDay(
                    $dayCursor->copy()->startOfDay(),
                    $count,
                    $hourFrom,
                    $hourTo,
                    null
                );
            }

            foreach ($times as $publishAt) {
                $review = $reviews->get($cursor);
                if (!$review instanceof Review) {
                    continue;
                }

                $this->scheduleService->schedule($review, $publishAt, true);
                $scheduled++;
                $cursor++;
            }

            $dayCursor = $dayCursor->copy()->addDay()->startOfDay();
            $firstDay = false;
        }

        return $scheduled;
    }

    protected function buildPublicationQueue(Collection $reviews): Collection
    {
        $buckets = $reviews
            ->groupBy(fn (Review $review) => $this->reviewableKey($review))
            ->map(fn (Collection $bucket) => $bucket->shuffle()->values());

        $queue = collect();
        $lastKey = null;

        while ($buckets->isNotEmpty()) {
            $cycleKeys = $buckets->keys()->shuffle()->values()->all();
            $cycleKeys = $this->avoidImmediateRepeat($cycleKeys, $lastKey);

            foreach ($cycleKeys as $key) {
                /** @var \Illuminate\Support\Collection<int, \Backpack\Reviews\app\Models\Review> $bucket */
                $bucket = $buckets->get($key);
                $review = $bucket?->shift();

                if (!$review instanceof Review) {
                    $buckets->forget($key);
                    continue;
                }

                $queue->push($review);
                $lastKey = $key;

                if ($bucket->isEmpty()) {
                    $buckets->forget($key);
                    continue;
                }

                $buckets->put($key, $bucket->values());
            }
        }

        return $queue->values();
    }

    protected function avoidImmediateRepeat(array $cycleKeys, ?string $lastKey): array
    {
        if ($lastKey === null || count($cycleKeys) < 2 || $cycleKeys[0] !== $lastKey) {
            return $cycleKeys;
        }

        $swapIndex = random_int(1, count($cycleKeys) - 1);
        [$cycleKeys[0], $cycleKeys[$swapIndex]] = [$cycleKeys[$swapIndex], $cycleKeys[0]];

        return $cycleKeys;
    }

    protected function reviewableKey(Review $review): string
    {
        return sprintf(
            '%s:%s',
            $review->reviewable_type ?: $review->getMorphClass(),
            (string) ($review->reviewable_id ?: $review->getKey())
        );
    }

    protected function buildDailyPlans(int $totalReviews, int $minPerDay, int $maxPerDay): array
    {
        $minPerDay = max(1, $minPerDay);
        $maxPerDay = max($minPerDay, $maxPerDay);
        $days = max(1, (int) ceil($totalReviews / $maxPerDay));

        $plans = [];
        $remainingReviews = $totalReviews;

        for ($dayIndex = 0; $dayIndex < $days; $dayIndex++) {
            $remainingDays = $days - $dayIndex;

            $baseLowerBound = max(1, $remainingReviews - (($remainingDays - 1) * $maxPerDay));
            $baseUpperBound = min($maxPerDay, $remainingReviews - ($remainingDays - 1));

            $preferredLowerBound = min($maxPerDay, $minPerDay);
            $lowerBound = max($baseLowerBound, min($preferredLowerBound, $baseUpperBound));

            $plans[] = random_int($lowerBound, $baseUpperBound);
            $remainingReviews -= $plans[array_key_last($plans)];
        }

        shuffle($plans);

        return $plans;
    }

    protected function buildPublishTimesForDay(
        Carbon $day,
        int $count,
        int $hourFrom,
        int $hourTo,
        ?Carbon $notBefore = null
    ): array {
        $hourFrom = max(0, min(23, $hourFrom));
        $hourTo = max($hourFrom, min(23, $hourTo));

        $windowStart = $day->copy()->setTime($hourFrom, 0, 0);
        $windowEnd = $day->copy()->setTime($hourTo, 59, 59);

        if ($notBefore instanceof Carbon && $notBefore->greaterThan($windowStart)) {
            $windowStart = $notBefore->copy();
        }

        $minimumLeadTime = now()->copy()->addMinutes(5);
        if ($minimumLeadTime->greaterThan($windowStart) && $minimumLeadTime->isSameDay($day)) {
            $windowStart = $minimumLeadTime;
        }

        if ($windowStart->greaterThan($windowEnd)) {
            return [];
        }

        $timestamps = [];
        $startTimestamp = $windowStart->getTimestamp();
        $endTimestamp = $windowEnd->getTimestamp();

        for ($index = 0; $index < $count; $index++) {
            $timestamps[] = random_int($startTimestamp, $endTimestamp);
        }

        sort($timestamps);

        return array_map(
            fn (int $timestamp, int $index) => Carbon::createFromTimestamp($timestamp)->addSeconds($index),
            $timestamps,
            array_keys($timestamps)
        );
    }
}
