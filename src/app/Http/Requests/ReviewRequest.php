<?php

namespace Backpack\Reviews\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['photo_gallery', 'video_poster'] as $attribute) {
            if (!$this->exists($attribute)) {
                continue;
            }

            $payload[$attribute] = $this->normalizeImageCollectionInput($this->input($attribute));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $type = $this->type;
        
        $this->redirect = url()->previous().'#review_' . $type;
      
        return [
          'text' => 'nullable|string|min:2|max:1000|required_without_all:video_url,photo_gallery',
          'review_type' => 'nullable|string|in:text,video,photo',
          'is_video' => 'nullable|boolean',
          'video_url' => 'nullable|url|max:2048|required_if:is_video,1,true,on|required_if:review_type,video',
          'video_title' => 'nullable',
          'video_poster' => 'nullable',
          'photo_gallery' => 'nullable|array|max:5|required_if:review_type,photo',
          'photo_gallery.*.src' => 'required_with:photo_gallery|string',
          'lang' => 'nullable|string|min:2|max:5',
          'country' => 'nullable|string|size:2',
          'created_at' => 'nullable|date',
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'text_review_text' => 'review text'
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            //
        ];
    }

    protected function normalizeImageCollectionInput($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                return [['src' => $value]];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        if ($this->looksLikeImageItem($value)) {
            $value = [$value];
        }

        return array_values(array_filter(array_map(function ($item) {
            return $this->normalizeImageItem($item);
        }, $value)));
    }

    protected function normalizeImageItem($item): ?array
    {
        if (is_string($item)) {
            $item = trim($item);

            return $item !== '' ? ['src' => $item] : null;
        }

        if (is_object($item)) {
            $item = (array) $item;
        }

        if (!is_array($item) || !$this->looksLikeImageItem($item)) {
            return null;
        }

        if (!array_key_exists('src', $item) && array_key_exists('path', $item)) {
            $item['src'] = $item['path'];
        }

        if (array_key_exists('src', $item) && is_string($item['src'])) {
            $item['src'] = trim($item['src']);
        }

        return $item;
    }

    protected function looksLikeImageItem(array $item): bool
    {
        foreach (['src', 'path', 'alt', 'title', 'size'] as $key) {
            if (array_key_exists($key, $item)) {
                return true;
            }
        }

        return false;
    }
}
