<?php

namespace Backpack\Reviews\app\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleReviewAvatarStorage
{
    protected const DISK = 'public';
    protected const FOLDER = 'reviews/google-avatars';

    public function storeFromSource(?string $source, ?string $identifier = null): ?array
    {
        $source = trim((string) $source);
        if ($source === '') {
            return null;
        }

        if (Str::startsWith($source, 'data:image')) {
            return $this->storeFromBase64($source, $identifier);
        }

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return $this->storeFromRemoteUrl($source, $identifier);
        }

        return null;
    }

    public function storeFromBase64(string $base64, ?string $identifier = null): ?array
    {
        if (!preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $base64, $matches)) {
            return null;
        }

        $binary = base64_decode(substr($base64, strpos($base64, ',') + 1), true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $extension = $this->normalizeExtension($matches[1] ?? null);
        return $this->storeBinary($binary, $extension, $identifier);
    }

    public function storeFromRemoteUrl(string $url, ?string $identifier = null): ?array
    {
        try {
            $response = Http::timeout(20)->get($url);
        } catch (\Throwable $e) {
            Log::warning('google_reviews.avatar.download_failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$response->ok()) {
            Log::warning('google_reviews.avatar.download_http_error', [
                'url' => $url,
                'status' => $response->status(),
            ]);
            return null;
        }

        $body = $response->body();
        if ($body === '') {
            return null;
        }

        $extension = $this->normalizeExtensionFromResponse($response->header('Content-Type'), $url);
        return $this->storeBinary($body, $extension, $identifier);
    }

    protected function storeBinary(string $binary, string $extension, ?string $identifier = null): ?array
    {
        $idPart = Str::slug((string) $identifier);
        if ($idPart === '') {
            $idPart = Str::random(10);
        }

        $path = sprintf(
            '%s/%s/%s-%s.%s',
            self::FOLDER,
            now()->format('Y/m'),
            $idPart,
            Str::random(10),
            $extension
        );

        Storage::disk(self::DISK)->put($path, $binary, 'public');

        return [
            'path' => $path,
            'url' => Storage::disk(self::DISK)->url($path),
        ];
    }

    protected function normalizeExtensionFromResponse(?string $contentType, string $url): string
    {
        $mime = strtolower(trim(explode(';', (string) $contentType)[0]));

        $mapped = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => null,
        };

        if ($mapped) {
            return $mapped;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $ext = pathinfo((string) $path, PATHINFO_EXTENSION);

        return $this->normalizeExtension($ext);
    }

    protected function normalizeExtension(?string $extension): string
    {
        $ext = strtolower((string) $extension);
        $ext = match ($ext) {
            'jpeg' => 'jpg',
            'svg+xml' => 'svg',
            default => $ext,
        };

        if (!in_array($ext, ['jpg', 'png', 'webp', 'gif', 'svg'], true)) {
            return 'jpg';
        }

        return $ext;
    }
}
