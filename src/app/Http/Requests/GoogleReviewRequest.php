<?php

namespace Backpack\Reviews\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class GoogleReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_name' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:ak_google_review_locations,id'],
            'location_name' => ['required_without:location_id', 'string', 'max:255'],
            'review_id' => ['nullable', 'string', 'max:255'],
            'reviewer_name' => ['nullable', 'string', 'max:255'],
            'reviewer_photo_url' => ['nullable', 'string'],
            'reviewer_is_anonymous' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
            'reply_comment' => ['nullable', 'string'],
            'review_created_at' => ['nullable', 'date'],
            'review_updated_at' => ['nullable', 'date'],
            'reply_updated_at' => ['nullable', 'date'],
            'synced_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'location_name' => $this->filled('location_name')
                ? trim((string) $this->input('location_name'))
                : $this->input('location_name'),
            'reviewer_name' => $this->filled('reviewer_name')
                ? trim((string) $this->input('reviewer_name'))
                : $this->input('reviewer_name'),
            'reviewer_photo_url' => $this->filled('reviewer_photo_url')
                ? trim((string) $this->input('reviewer_photo_url'))
                : $this->input('reviewer_photo_url'),
        ];

        if ($this->isMethod('post') && blank($this->input('review_name'))) {
            $payload['review_name'] = 'manual/' . Str::uuid()->toString();
        }

        $this->merge($payload);
    }
}
