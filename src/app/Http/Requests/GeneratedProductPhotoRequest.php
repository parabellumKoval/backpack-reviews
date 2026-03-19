<?php

namespace Backpack\Reviews\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratedProductPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|min:1',
            'status' => 'required|string|max:32|in:pending_review,approved,rejected,failed',
            'image' => 'nullable',
            'prompt' => 'nullable|string',
            'error_message' => 'nullable|string',
        ];
    }
}
