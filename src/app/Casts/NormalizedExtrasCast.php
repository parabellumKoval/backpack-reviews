<?php

namespace Backpack\Reviews\app\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class NormalizedExtrasCast implements CastsAttributes
{
    /**
     * Cast the given value (from database).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        // Value should be a JSON string from database, but could be array if already decoded
        if (is_array($value)) {
            $extras = $value;
        } elseif (is_string($value)) {
            $extras = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
        } else {
            return [];
        }

        if (!is_array($extras)) {
            return [];
        }

        // Normalize advantages and flaws to strings (they may be stored as arrays from AI generation)
        foreach (['advantages', 'flaws'] as $field) {
            if (isset($extras[$field]) && is_array($extras[$field])) {
                $extras[$field] = implode("\n", array_filter($extras[$field], 'is_string'));
            }
        }

        return $extras;
    }

    /**
     * Prepare the given value for storage (to database).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // If it's already a string (JSON), decode it first to normalize
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return null;
        }

        // Normalize advantages and flaws to strings before saving
        foreach (['advantages', 'flaws'] as $field) {
            if (isset($value[$field]) && is_array($value[$field])) {
                $value[$field] = implode("\n", array_filter($value[$field], 'is_string'));
            }
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
