<?php

namespace Backpack\Reviews\app\Services;

use App\Models\Product;
use Backpack\Reviews\app\Models\GeneratedProductPhoto;
use Backpack\Reviews\app\Models\Review;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ParabellumKoval\BackpackImages\DTO\StoredImage;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

class GeneratedProductPhotoReviewService
{
    public function __construct(
        private readonly ImageUploader $imageUploader,
        private readonly ReviewProductTargetResolver $productTargetResolver
    ) {
    }

    public function attachFirstApprovedPhoto(Review $review, Product|int $product): bool
    {
        $familyProductIds = $this->productTargetResolver->familyProductIds($product);
        $canonicalProductId = $this->productTargetResolver->canonicalProductId($product);

        $candidate = GeneratedProductPhoto::query()
            ->approved()
            ->whereIn('product_id', $familyProductIds)
            ->orderByRaw('CASE WHEN product_id = ? THEN 0 ELSE 1 END', [$canonicalProductId])
            ->orderBy('id')
            ->first();

        if (!$candidate instanceof GeneratedProductPhoto) {
            return false;
        }

        $sourceImage = $candidate->getFirstImage('image');
        if (!is_array($sourceImage)) {
            return false;
        }

        $stored = null;

        try {
            $stored = $this->transferCandidateImage($candidate, $sourceImage);
            if (!$stored instanceof StoredImage) {
                Log::warning('Failed to resolve generated product photo source file.', [
                    'review_id' => $review->getKey(),
                    'product_id' => $canonicalProductId,
                    'generated_photo_id' => $candidate->getKey(),
                    'source_path' => Arr::get($sourceImage, 'src'),
                    'source_url' => $candidate->getFirstImageForApi('image')['src'] ?? null,
                ]);

                return false;
            }

            $review->review_type = 'photo';
            $review->photo_gallery = [[
                'src' => $stored->path,
                'alt' => $sourceImage['alt'] ?? null,
                'title' => $sourceImage['title'] ?? null,
            ]];
            $review->save();

            $this->deleteSourceImage($sourceImage);
            $candidate->delete();

            return true;
        } catch (Throwable $exception) {
            if ($stored instanceof StoredImage) {
                $this->imageUploader->delete($stored->path);
            }

            Log::warning('Failed to attach generated product photo to review.', [
                'review_id' => $review->getKey(),
                'product_id' => $canonicalProductId,
                'generated_photo_id' => $candidate->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    protected function transferCandidateImage(GeneratedProductPhoto $candidate, array $sourceImage): ?StoredImage
    {
        [$file, $cleanup] = $this->resolveSourceFile($candidate, $sourceImage);

        if (!$file instanceof File) {
            return null;
        }

        try {
            return $this->imageUploader->uploadFromFile(
                $file,
                new ImageUploadOptions(folder: 'reviews/photos')
            );
        } finally {
            if (is_callable($cleanup)) {
                $cleanup();
            }
        }
    }

    protected function resolveSourceFile(GeneratedProductPhoto $candidate, array $sourceImage): array
    {
        $path = trim((string) Arr::get($sourceImage, 'src', ''));
        $disk = $this->resolveLocalDisk();

        if ($disk && $path !== '') {
            foreach ($this->possibleLocalDiskPaths($path) as $diskPath) {
                if (Storage::disk($disk)->exists($diskPath)) {
                    $absolutePath = Storage::disk($disk)->path($diskPath);

                    if (is_file($absolutePath) && is_readable($absolutePath)) {
                        return [new File($absolutePath), null];
                    }
                }
            }
        }

        $url = $candidate->getFirstImageForApi('image')['src'] ?? null;
        if (!is_string($url) || trim($url) === '') {
            return [null, null];
        }

        return $this->downloadToTemporaryFile($url, basename($path ?: parse_url($url, PHP_URL_PATH) ?: 'review-photo.jpg'));
    }

    protected function resolveLocalDisk(): ?string
    {
        $provider = (string) config('backpack-images.default_provider', 'local');

        if ($provider !== 'local') {
            return null;
        }

        $disk = config('backpack-images.providers.local.disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    protected function downloadToTemporaryFile(string $url, ?string $desiredName = null): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'review-photo-');

        if ($tempPath === false) {
            return [null, null];
        }

        try {
            $response = Http::timeout(60)->connectTimeout(30)->retry(2, 100)->sink($tempPath)->get($url);

            if (!$response->successful()) {
                @unlink($tempPath);

                return [null, null];
            }
        } catch (Throwable) {
            @unlink($tempPath);

            return [null, null];
        }

        if ($desiredName) {
            $targetPath = dirname($tempPath) . DIRECTORY_SEPARATOR . ltrim($desiredName, DIRECTORY_SEPARATOR);

            if (file_exists($targetPath)) {
                $targetPath = dirname($tempPath) . DIRECTORY_SEPARATOR . uniqid('review-photo-', true) . '-' . basename($desiredName);
            }

            if (@rename($tempPath, $targetPath)) {
                $tempPath = $targetPath;
            }
        }

        return [
            new File($tempPath),
            static function () use ($tempPath): void {
                @unlink($tempPath);
            },
        ];
    }

    protected function deleteSourceImage(array $sourceImage): void
    {
        $path = trim((string) Arr::get($sourceImage, 'src', ''));

        if ($path === '') {
            return;
        }

        try {
            $disk = $this->resolveLocalDisk();

            if ($disk !== null) {
                Storage::disk($disk)->delete($this->possibleLocalDiskPaths($path));

                return;
            }

            $this->imageUploader->delete($path);
        } catch (Throwable) {
            Log::warning('Failed to delete consumed generated product photo file.', [
                'path' => $path,
            ]);
        }
    }

    protected function possibleLocalDiskPaths(string $path): array
    {
        $path = ltrim(trim($path), '/');
        $defaultFolder = trim((string) config('backpack-images.default_folder', 'images'), '/');

        if ($path === '') {
            return [];
        }

        $variants = [$path];

        if ($defaultFolder !== '') {
            if (str_starts_with($path, $defaultFolder . '/')) {
                $variants[] = substr($path, strlen($defaultFolder) + 1);
            } else {
                $variants[] = $defaultFolder . '/' . $path;
            }
        }

        return collect($variants)
            ->filter(fn ($item) => is_string($item) && $item !== '')
            ->values()
            ->unique()
            ->all();
    }
}
