<?php

namespace Backpack\Reviews\app\Services;

use Intervention\Image\ImageManager;

class GeneratedPhotoImageProcessor
{
    public function cropWatermarkArea(string $dataUri, float $cropRightPercent, float $cropBottomPercent): string
    {
        $parsed = $this->parseDataUri($dataUri);
        if ($parsed === null) {
            return $dataUri;
        }

        try {
            $image = ImageManager::gd()->read($parsed['binary']);
        } catch (\Throwable) {
            return $dataUri;
        }

        $width = $image->width();
        $height = $image->height();

        if ($width < 20 || $height < 20) {
            return $dataUri;
        }

        $right = max(0, min(20, $cropRightPercent));
        $bottom = max(0, min(20, $cropBottomPercent));

        if ($right <= 0 && $bottom <= 0) {
            return $dataUri;
        }

        $targetWidth = (int) max(1, floor($width * (1 - ($right / 100))));
        $targetHeight = (int) max(1, floor($height * (1 - ($bottom / 100))));

        if ($targetWidth >= $width && $targetHeight >= $height) {
            return $dataUri;
        }

        $cropped = $image->crop($targetWidth, $targetHeight, 0, 0);
        $encoded = $cropped->toJpg(quality: 84);

        return 'data:image/jpeg;base64,' . base64_encode((string) $encoded);
    }

    protected function parseDataUri(string $dataUri): ?array
    {
        if (!preg_match('#^data:(?P<mime>[^;]+);base64,(?P<data>.+)$#si', trim($dataUri), $matches)) {
            return null;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', (string) ($matches['data'] ?? '')), true);
        if ($binary === false) {
            return null;
        }

        return [
            'mime' => trim((string) ($matches['mime'] ?? 'image/jpeg')),
            'binary' => $binary,
        ];
    }
}
