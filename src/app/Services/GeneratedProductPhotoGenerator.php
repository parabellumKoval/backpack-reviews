<?php

namespace Backpack\Reviews\app\Services;

use App\Models\Product;
use Backpack\Reviews\app\Models\GeneratedProductPhoto;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;

class GeneratedProductPhotoGenerator
{
    public function __construct(
        private readonly ContentGenerator $generator,
        private readonly ImageUploader $imageUploader,
        private readonly GeneratedPhotoPromptFactory $promptFactory,
        private readonly GeneratedPhotoImageProcessor $imageProcessor,
        private readonly ReviewProductTargetResolver $productTargetResolver,
    ) {
    }

    public function generate(Product $product, array $options = []): array
    {
        $reference = $this->resolveReferenceImage($product);
        if ($reference === null) {
            return [
                'status' => 'skipped',
                'message' => 'No reference packaging image found.',
            ];
        }

        $promptPayload = $this->promptFactory->build($product, $options);
        $prompt = (string) ($promptPayload['prompt'] ?? '');

        if ($prompt === '') {
            return [
                'status' => 'failed',
                'message' => 'Unable to build generation prompt.',
            ];
        }

        $imageDriver = (string) ($options['image_driver'] ?? config('backpack.reviews.generated_product_photos.image_driver', 'gemini'));
        $imageModel = $options['image_model'] ?? config('backpack.reviews.generated_product_photos.image_model');

        $payload = [
            'prompt' => $prompt,
            'driver' => $imageDriver,
            'response_format' => 'image',
            'output_type' => 'collection',
            'quantity' => 1,
            'payload' => [
                'input_images' => [
                    ['url' => $reference['url']],
                ],
                'response_modalities' => ['IMAGE'],
            ],
        ];

        if (is_string($imageModel) && trim($imageModel) !== '') {
            $payload['model'] = trim($imageModel);
        }

        try {
            $response = $this->generator->generate($payload);
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'prompt' => $prompt,
                'prompt_context' => $promptPayload['context'] ?? [],
                'driver' => $imageDriver,
                'model' => $payload['model'] ?? null,
                'reference' => $reference,
            ];
        }

        $images = $response->result;
        if (is_array($images) && isset($images['data_uri'])) {
            $images = [$images];
        }

        if (!is_array($images) || $images === []) {
            return [
                'status' => 'failed',
                'message' => 'Image generation returned an empty result.',
                'prompt' => $prompt,
                'prompt_context' => $promptPayload['context'] ?? [],
                'driver' => $imageDriver,
                'model' => $payload['model'] ?? null,
                'reference' => $reference,
            ];
        }

        $firstImage = $images[0];
        $dataUri = is_array($firstImage) ? ($firstImage['data_uri'] ?? null) : null;
        if (!is_string($dataUri) || trim($dataUri) === '') {
            return [
                'status' => 'failed',
                'message' => 'No image payload found in provider response.',
                'prompt' => $prompt,
                'prompt_context' => $promptPayload['context'] ?? [],
                'driver' => $imageDriver,
                'model' => $payload['model'] ?? null,
                'reference' => $reference,
            ];
        }

        $cropRight = (float) ($options['watermark_crop_right_percent'] ?? config('backpack.reviews.generated_product_photos.watermark_crop_right_percent', 3));
        $cropBottom = (float) ($options['watermark_crop_bottom_percent'] ?? config('backpack.reviews.generated_product_photos.watermark_crop_bottom_percent', 3));

        $processedDataUri = $this->imageProcessor->cropWatermarkArea($dataUri, $cropRight, $cropBottom);

        try {
            $stored = $this->imageUploader->uploadFromBase64(
                $processedDataUri,
                new ImageUploadOptions(folder: 'reviews/generated-product-photos')
            );
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'Failed to store generated image: ' . $exception->getMessage(),
                'prompt' => $prompt,
                'prompt_context' => $promptPayload['context'] ?? [],
                'driver' => $imageDriver,
                'model' => $payload['model'] ?? null,
                'reference' => $reference,
            ];
        }

        $canonicalProductId = $this->productTargetResolver->canonicalProductId($product);

        $record = GeneratedProductPhoto::query()->create([
            'product_id' => $canonicalProductId,
            'image' => [
                [
                    'src' => $stored->path,
                    'alt' => sprintf('Generated product photo for %s', $product->name ?? ('Product #' . $product->id)),
                ],
            ],
            'status' => GeneratedProductPhoto::STATUS_PENDING_REVIEW,
            'prompt' => $prompt,
            'prompt_context' => $promptPayload['context'] ?? [],
            'reference_image_url' => $reference['url'],
            'reference_image_path' => $reference['path'],
            'driver' => $imageDriver,
            'model' => $payload['model'] ?? null,
            'generation_run_id' => $options['generation_run_id'] ?? null,
        ]);

        return [
            'status' => 'success',
            'record' => $record,
            'prompt' => $prompt,
            'driver' => $imageDriver,
            'model' => $payload['model'] ?? null,
            'reference' => $reference,
        ];
    }

    protected function resolveReferenceImage(Product $product): ?array
    {
        $image = $product->getFirstImageForApi();
        $url = is_array($image) ? ($image['src'] ?? null) : null;

        if (!is_string($url) || trim($url) === '') {
            return null;
        }

        $rawImage = $product->getFirstImage();
        $path = is_array($rawImage) ? ($rawImage['src'] ?? null) : null;

        return [
            'url' => trim($url),
            'path' => is_string($path) ? trim($path) : null,
        ];
    }
}
