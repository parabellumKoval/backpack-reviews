<?php

namespace Backpack\Reviews\app\Console\Commands;

use App\Models\Product;
use App\Support\GenerationRunReporter;
use Backpack\Reviews\app\Models\GeneratedProductPhoto;
use Backpack\Reviews\app\Services\GeneratedProductPhotoGenerator;
use Backpack\Reviews\app\Services\ReviewProductTargetResolver;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;

class GenerateProductReviewPhotos extends Command
{
    protected $signature = 'reviews:generate-product-photos
        {--product= : Product ID to generate photos for}
        {--products=* : Product IDs to generate photos for}
        {--category= : Category ID to generate photos for all products in it}
        {--brand= : Brand ID to generate photos for all products of it}
        {--all : Generate photos for all products}
        {--limit= : Maximum number of products to process after filtering}
        {--photos-per-product=10 : Number of photos to generate per product}
        {--photos-limit-total= : Hard cap for total generated photos in this run}
        {--skip-existing : Skip or reduce generation for products that already have photos}
        {--validate-reference : Validate reference image suitability before generation}
        {--ai-prompt-variations : Use AI to diversify prompt wording}
        {--watermark-crop-right-percent=3 : Crop this percent from right side}
        {--watermark-crop-bottom-percent=3 : Crop this percent from bottom side}
        {--run-id= : Internal generation run ID for progress tracking}
        {--image-driver= : AI driver for image generation}
        {--image-model= : AI model for image generation}
        {--prompt-driver= : AI driver for prompt rewriting}
        {--prompt-model= : AI model for prompt rewriting}
        {--dry-run : Do not write generated records}';

    protected $description = 'Generate non-professional user-style product photos from packaging references';

    public function __construct(
        private readonly GeneratedProductPhotoGenerator $photoGenerator,
        private readonly ContentGenerator $contentGenerator,
        private readonly ReviewProductTargetResolver $productTargetResolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $productId = $this->option('product');
        $categoryId = $this->option('category');
        $brandId = $this->option('brand');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');
        $validateReference = (bool) $this->option('validate-reference');
        $reporter = GenerationRunReporter::fromOption($this->option('run-id'));

        $photosPerProduct = max(1, (int) $this->option('photos-per-product'));
        $photosLimitTotal = $this->normalizeNullableInt($this->option('photos-limit-total'));

        if (!$productId && $this->normalizeProductIds($this->option('products')) === [] && !$categoryId && !$brandId && !$all) {
            $this->error('You must specify --product, --products, --category, --brand, or --all');
            return self::FAILURE;
        }

        $productIds = $this->resolveProductIds(
            $productId,
            $this->option('products'),
            $categoryId,
            $brandId,
            $all,
            $this->option('limit'),
        );

        if ($productIds === []) {
            $this->error('No products found for the given criteria.');
            return self::FAILURE;
        }

        $totalProducts = count($productIds);

        $reporter->setTotal($totalProducts, [
            'generated_photos' => 0,
            'failed_products' => 0,
            'skipped_products' => 0,
            'photos_per_product' => $photosPerProduct,
            'photos_limit_total' => $photosLimitTotal,
            'dry_run' => $dryRun,
        ]);

        $this->info(sprintf('Found %d products for photo generation.', $totalProducts));

        if ($dryRun) {
            $this->warn('Dry run mode: no photos will be saved.');
        }

        $generatedPhotos = 0;
        $failedProducts = 0;
        $skippedProducts = 0;
        $processedProducts = 0;

        $progress = $this->output->createProgressBar($totalProducts);
        $progress->start();

        foreach ($this->selectedProducts($productIds) as $product) {
            if ($photosLimitTotal !== null && $generatedPhotos >= $photosLimitTotal) {
                break;
            }

            $existingCount = $this->existingPhotosCount($product);
            $plannedCount = $photosPerProduct;

            if ($skipExisting) {
                $plannedCount = max(0, $photosPerProduct - $existingCount);
            }

            if ($plannedCount < 1) {
                $skippedProducts++;
                $processedProducts++;
                $progress->advance();
                $reporter->setProgress($processedProducts, null, [
                    'generated_photos' => $generatedPhotos,
                    'failed_products' => $failedProducts,
                    'skipped_products' => $skippedProducts,
                ]);
                continue;
            }

            if ($validateReference && !$this->referenceLooksSuitable($product)) {
                $skippedProducts++;
                $processedProducts++;
                $progress->advance();
                $reporter->setProgress($processedProducts, null, [
                    'generated_photos' => $generatedPhotos,
                    'failed_products' => $failedProducts,
                    'skipped_products' => $skippedProducts,
                ]);
                continue;
            }

            $failedForCurrentProduct = false;

            for ($index = 0; $index < $plannedCount; $index++) {
                if ($photosLimitTotal !== null && $generatedPhotos >= $photosLimitTotal) {
                    break;
                }

                if ($dryRun) {
                    $generatedPhotos++;
                    continue;
                }

                $result = $this->photoGenerator->generate($product, [
                    'generation_run_id' => $this->normalizeRunId($this->option('run-id')),
                    'image_driver' => $this->option('image-driver'),
                    'image_model' => $this->option('image-model'),
                    'prompt_driver' => $this->option('prompt-driver'),
                    'prompt_model' => $this->option('prompt-model'),
                    'ai_prompt_variations' => (bool) $this->option('ai-prompt-variations'),
                    'watermark_crop_right_percent' => (float) $this->option('watermark-crop-right-percent'),
                    'watermark_crop_bottom_percent' => (float) $this->option('watermark-crop-bottom-percent'),
                ]);

                $status = (string) ($result['status'] ?? 'failed');

                if ($status === 'success') {
                    $generatedPhotos++;
                    continue;
                }

                if ($status === 'skipped') {
                    $failedForCurrentProduct = true;
                    continue;
                }

                $failedForCurrentProduct = true;
                $this->storeFailureRecord($product, $result);
            }

            if ($failedForCurrentProduct) {
                $failedProducts++;
            }

            $processedProducts++;
            $progress->advance();
            $reporter->setProgress($processedProducts, null, [
                'generated_photos' => $generatedPhotos,
                'failed_products' => $failedProducts,
                'skipped_products' => $skippedProducts,
            ]);
        }

        $progress->finish();
        $this->newLine(2);

        $this->info(sprintf('✓ Generated %d product photos.', $generatedPhotos));

        if ($failedProducts > 0) {
            $this->warn(sprintf('○ Products with failures: %d', $failedProducts));
        }

        if ($skippedProducts > 0) {
            $this->line(sprintf('○ Skipped products: %d', $skippedProducts));
        }

        $reporter->merge([
            'generated_photos' => $generatedPhotos,
            'failed_products' => $failedProducts,
            'skipped_products' => $skippedProducts,
        ], [
            'generated_photos' => $generatedPhotos,
            'failed_products' => $failedProducts,
            'skipped_products' => $skippedProducts,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }

    protected function resolveProductIds(
        ?string $productId,
        mixed $productIds,
        ?string $categoryId,
        ?string $brandId,
        bool $all,
        mixed $limit,
    ): array {
        $query = Product::query()
            ->where('is_active', true)
            ->select(['id', 'parent_id']);

        if ($productId) {
            return $this->canonicalIdsFromProducts(
                $query->whereKey((int) $productId)->get()
            );
        }

        $productIds = $this->normalizeProductIds($productIds);
        if ($productIds !== []) {
            return $this->canonicalIdsFromRequestedProducts($productIds);
        }

        if ($categoryId) {
            $categoryIds = Category::getCategoryNodeIdList(null, (int) $categoryId);
            if (empty($categoryIds)) {
                return [];
            }

            $query->whereHas('categories', fn ($q) => $q->whereIn('ak_product_categories.id', $categoryIds));
        } elseif ($brandId) {
            if (!Brand::query()->whereKey((int) $brandId)->exists()) {
                return [];
            }

            $query->where('brand_id', (int) $brandId);
        } elseif (!$all) {
            return [];
        }

        $limit = $this->normalizeNullableInt($limit);
        $excludedProductIds = collect((array) config('backpack.reviews.generated_product_photos.excluded_product_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return $this->collectPrioritizedCanonicalProductIds(
            $query->withCount('reviews')
                ->orderByDesc('reviews_count')
                ->orderBy('id'),
            $limit,
            $excludedProductIds->all(),
        );
    }

    protected function existingPhotosCount(Product $product): int
    {
        $familyProductIds = $this->productTargetResolver->familyProductIds($product);

        return GeneratedProductPhoto::query()
            ->whereIn('product_id', $familyProductIds)
            ->whereIn('status', [
                GeneratedProductPhoto::STATUS_PENDING_REVIEW,
                GeneratedProductPhoto::STATUS_APPROVED,
            ])
            ->count();
    }

    protected function storeFailureRecord(Product $product, array $result): void
    {
        try {
            GeneratedProductPhoto::query()->create([
                'product_id' => $this->productTargetResolver->canonicalProductId($product),
                'status' => GeneratedProductPhoto::STATUS_FAILED,
                'prompt' => $result['prompt'] ?? null,
                'prompt_context' => $result['prompt_context'] ?? [],
                'reference_image_url' => $result['reference']['url'] ?? null,
                'reference_image_path' => $result['reference']['path'] ?? null,
                'driver' => $result['driver'] ?? null,
                'model' => $result['model'] ?? null,
                'error_message' => Str::limit((string) ($result['message'] ?? 'Unknown generation error.'), 1500, '...'),
                'generation_run_id' => $this->normalizeRunId($this->option('run-id')),
            ]);
        } catch (\Throwable) {
            // If logging failure row fails, keep command running.
        }
    }

    protected function referenceLooksSuitable(Product $product): bool
    {
        $reference = $product->getFirstImageForApi();
        $referenceUrl = is_array($reference) ? ($reference['src'] ?? null) : null;

        if (!is_string($referenceUrl) || trim($referenceUrl) === '') {
            return false;
        }

        $payload = [
            'prompt' => 'Check if the uploaded image is mainly a product package photo. Return JSON: {"is_suitable":true|false,"reason":"..."}.',
            'response_format' => 'array',
            'output_type' => 'single',
            'payload' => [
                'input_images' => [
                    ['url' => trim($referenceUrl)],
                ],
            ],
        ];

        if ($this->option('prompt-driver')) {
            $payload['driver'] = (string) $this->option('prompt-driver');
        }

        if ($this->option('prompt-model')) {
            $payload['model'] = (string) $this->option('prompt-model');
        }

        try {
            $response = $this->contentGenerator->generate($payload);
            $result = is_array($response->result) ? $response->result : [];

            return (bool) ($result['is_suitable'] ?? false);
        } catch (\Throwable) {
            return true;
        }
    }

    protected function normalizeProductIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($values as $entry) {
            foreach (explode(',', (string) $entry) as $part) {
                $part = trim($part);
                if ($part === '' || !ctype_digit($part)) {
                    continue;
                }

                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    protected function normalizeRunId(mixed $runId): ?int
    {
        if (!is_numeric($runId)) {
            return null;
        }

        $normalized = (int) $runId;

        return $normalized > 0 ? $normalized : null;
    }

    protected function selectedProducts(array $productIds, int $chunkSize = 25): \Generator
    {
        foreach (array_chunk($productIds, $chunkSize) as $chunk) {
            $products = Product::query()
                ->without(['ap', 'suppliers'])
                ->whereKey($chunk)
                ->with(['brand', 'categories', 'parent'])
                ->withCount('reviews')
                ->get()
                ->keyBy(fn (Product $product) => (int) $product->getKey());

            foreach ($chunk as $productId) {
                $product = $products->get($productId);

                if ($product instanceof Product) {
                    yield $product;
                }
            }
        }
    }

    protected function canonicalIdsFromRequestedProducts(array $productIds): array
    {
        $products = Product::query()
            ->select(['id', 'parent_id'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy(fn (Product $product) => (int) $product->getKey());

        $selected = [];
        $seen = [];

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if (!$product instanceof Product) {
                continue;
            }

            $canonicalId = $this->productTargetResolver->canonicalProductId($product);

            if (isset($seen[$canonicalId])) {
                continue;
            }

            $seen[$canonicalId] = true;
            $selected[] = $canonicalId;
        }

        return $selected;
    }

    protected function canonicalIdsFromProducts(Collection $products): array
    {
        $selected = [];
        $seen = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $canonicalId = $this->productTargetResolver->canonicalProductId($product);

            if (isset($seen[$canonicalId])) {
                continue;
            }

            $seen[$canonicalId] = true;
            $selected[] = $canonicalId;
        }

        return $selected;
    }

    protected function collectPrioritizedCanonicalProductIds(
        Builder $query,
        ?int $limit,
        array $excludedCanonicalIds = [],
    ): array {
        $selected = [];
        $seen = [];
        $excluded = array_fill_keys($excludedCanonicalIds, true);

        foreach ($query->cursor() as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $canonicalId = $this->productTargetResolver->canonicalProductId($product);

            if (isset($excluded[$canonicalId]) || isset($seen[$canonicalId])) {
                continue;
            }

            $seen[$canonicalId] = true;
            $selected[] = $canonicalId;

            if ($limit !== null && $limit > 0 && count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }
}
