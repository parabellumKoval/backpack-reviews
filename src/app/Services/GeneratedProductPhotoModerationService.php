<?php

namespace Backpack\Reviews\app\Services;

use Backpack\Reviews\app\Models\GeneratedProductPhoto;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use ParabellumKoval\BackpackImages\Services\ImageUploader;

class GeneratedProductPhotoModerationService
{
    public function __construct(private readonly ImageUploader $imageUploader)
    {
    }

    public function moderateBatch(Collection $batch, array $deleteIds, ?int $reviewedBy = null): array
    {
        $deleteIds = collect($deleteIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $deleted = 0;
        $approved = 0;

        foreach ($batch as $photo) {
            if (!$photo instanceof GeneratedProductPhoto) {
                continue;
            }

            if ($deleteIds->contains((int) $photo->id)) {
                $this->deleteImageFile($photo);
                $photo->forceFill([
                    'status' => GeneratedProductPhoto::STATUS_REJECTED,
                    'reviewed_by_id' => $reviewedBy,
                    'reviewed_at' => now(),
                ])->save();
                $photo->delete();
                $deleted++;
                continue;
            }

            $photo->markApproved($reviewedBy);
            $approved++;
        }

        return [
            'approved' => $approved,
            'deleted' => $deleted,
        ];
    }

    protected function deleteImageFile(GeneratedProductPhoto $photo): void
    {
        $image = $photo->getFirstImage('image');
        if (!is_array($image)) {
            return;
        }

        $path = Arr::get($image, 'src');
        if (!is_string($path) || trim($path) === '') {
            return;
        }

        try {
            $this->imageUploader->delete($path);
        } catch (\Throwable) {
            // Ignore file deletion failures; moderation flow should continue.
        }
    }
}
