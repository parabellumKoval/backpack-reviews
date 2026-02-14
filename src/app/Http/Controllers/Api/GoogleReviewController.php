<?php

namespace Backpack\Reviews\app\Http\Controllers\Api;

use Backpack\Reviews\app\Http\Resources\GoogleReviewResource;
use Backpack\Reviews\app\Models\GoogleReview;
use Illuminate\Http\Request;

class GoogleReviewController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $query = GoogleReview::query()->with('location');

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        if ($request->filled('location_name')) {
            $query->where('location_name', $request->input('location_name'));
        }

        if ($request->filled('account_id')) {
            $accountId = $request->input('account_id');
            $query->whereHas('location', function ($sub) use ($accountId) {
                $sub->where('account_id', $accountId);
            });
        }

        $total = (clone $query)->count();
        $avgRating = (clone $query)->avg('rating');

        $perPage = $request->input(
            'per_page',
            config('backpack.reviews.google.per_page', config('backpack.reviews.per_page', 12))
        );

        $paginator = $query->orderByDesc('review_created_at')->paginate($perPage);

        return GoogleReviewResource::collection($paginator)->additional([
            'meta' => [
                'total' => $total,
                'avg_rating' => $avgRating !== null ? round((float) $avgRating, 2) : null,
            ],
        ]);
    }

    public function show(GoogleReview $googleReview)
    {
        return new GoogleReviewResource($googleReview->load('location'));
    }
}
