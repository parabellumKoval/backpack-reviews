<?php

namespace Backpack\Reviews\app\Console\Commands;

use App\Models\Product;
use App\Support\GenerationRunReporter;
use Backpack\Profile\app\Models\Profile;
use Backpack\Reviews\app\Models\Review;
use Backpack\Reviews\app\Services\GeneratedProductPhotoReviewService;
use Backpack\Reviews\app\Services\GeneratedReviewScheduleService;
use Backpack\Reviews\app\Services\ReviewProductTargetResolver;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Category;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;
use App\Support\ReviewRewardContext;

class GenerateProductReviews extends Command
{
    protected $signature = 'reviews:generate
        {--product= : Product ID to generate reviews for}
        {--products=* : Product IDs to generate reviews for}
        {--category= : Category ID to generate reviews for all products in it}
        {--brand= : Brand ID to generate reviews for all products of it}
        {--all : Generate reviews for all products}
        {--review-count-max= : Include only products with moderated review count less than or equal to this value}
        {--limit= : Maximum number of products to process after filtering}
        {--min=5 : Minimum number of reviews per product}
        {--max=20 : Maximum number of reviews per product}
        {--country=* : Optional country code filter for bot profiles/reviews}
        {--locale=* : Optional locale filter for bot profiles/reviews}
        {--publish-now : Publish immediately instead of delayed scheduling}
        {--schedule-start=tomorrow : First publication day (strtotime-compatible)}
        {--schedule-min-per-day=1 : Minimum number of reviews to publish per day}
        {--schedule-max-per-day=1 : Maximum number of reviews to publish per day}
        {--schedule-hour-from=9 : Earliest publication hour}
        {--schedule-hour-to=21 : Latest publication hour}
        {--photo-review-chance=0/10 : Chance of turning a generated review into a photo review}
        {--run-id= : Internal generation run ID for progress tracking}
        {--driver= : AI driver name}
        {--model= : AI model name}
        {--temperature=0.9 : Model temperature (higher = more variety)}
        {--max-tokens= : Max tokens for the response}
        {--force : Force provider even if marked unavailable}
        {--prevent-duplicate-reviewers=1 : Prevent reusing the same reviewer for the same product if any review already exists in DB}
        {--prompt-path= : Path to prompt template file}
        {--skip-existing : Skip products that already have bot reviews}
        {--dry-run : Do not write to the database}';

    protected $description = 'Generate product reviews using AI from bot users (batch mode)';

    private const DEFAULT_PROMPT_PATH = __DIR__ . '/../../../resources/prompts/review-generator.txt';
    private const LANGUAGE_ALIASES = [
        'ua' => 'uk',
    ];
    private const LANGUAGE_MAP = [
        'uk' => 'Ukrainian',
        'cs' => 'Czech',
        'en' => 'English',
        'ru' => 'Russian',
        'de' => 'German',
        'es' => 'Spanish',
    ];
    private const REVIEW_BRIEF_ANGLES = [
        'tiny_thanks' => 'Very short thank-you review. Mention only that everything was good; no contrast, no explanation.',
        'effect_two_words' => 'Describe the effect in plain words. Keep it direct and simple.',
        'delivery_service' => 'Focus on delivery, packaging, or shop service more than the product effect.',
        'repeat_purchase' => 'Write as a repeat buyer. Mention buying again or already using it before.',
        'skeptical_surprise' => 'Start from a skeptical expectation, then say it turned out well. Do not use a mechanical "yes, but" sentence.',
        'gift_context' => 'Write as if it was bought for someone else or recommended to someone close.',
        'comparison' => 'Compare with a previous shop/product/alternative in a casual way.',
        'ritual' => 'Describe a small personal routine or moment of use.',
        'minor_detail' => 'Focus on one tiny concrete detail such as aroma, taste, packaging, texture, portion, or freshness.',
        'one_line_emotion' => 'One emotional line only, like a real quick mobile review.',
        'balanced_plain' => 'Plain balanced review with no dramatic claims and no contrast formula.',
        'practical_tip' => 'Include one practical user tip, short and natural.',
    ];
    private const REVIEW_BRIEF_STRUCTURES = [
        'fragment' => 'Sentence fragment or very short phrase; no full explanation.',
        'one_sentence' => 'Exactly one sentence.',
        'two_short_sentences' => 'Exactly two short sentences.',
        'question_answer' => 'Use a question-like opening or implied question, then answer naturally.',
        'starts_with_result' => 'Start directly with the result/effect, not with buying or trying.',
        'starts_with_context' => 'Start with context such as evening, workday, friend, package, or delivery.',
        'starts_with_recommendation' => 'Start with recommendation or verdict.',
        'no_intro' => 'No intro; write like a quick note after receiving/using the product.',
    ];
    private const REVIEW_BRIEF_LENGTHS = [
        '3-7 words',
        '8-14 words',
        'one short sentence',
        'two very short sentences',
        '2 sentences',
        '2-3 sentences',
    ];
    private const REVIEW_STYLE_MODES = [
        'one_word',
        'tiny_broken',
        'emoji_spam',
        'sloppy_lowercase',
        'camel_case',
        'punctuation_spam',
        'plain_medium',
        'longer_story',
        'typo_heavy',
        'clean_short',
    ];

    protected ContentGenerator $generator;
    protected string $promptTemplate;
    protected bool $dryRun = false;
    protected bool $publishNow = false;
    protected bool $preventDuplicateReviewers = true;
    protected string $batchId;
    protected int $photoReviewChanceNumerator = 0;
    protected int $photoReviewChanceDenominator = 10;
    protected ReviewProductTargetResolver $productTargetResolver;

    public function handle(
        ContentGenerator $generator,
        GeneratedReviewScheduleService $reviewScheduleService,
        GeneratedProductPhotoReviewService $generatedProductPhotoReviewService,
        ReviewProductTargetResolver $productTargetResolver
    ): int
    {
        $this->generator = $generator;
        $this->productTargetResolver = $productTargetResolver;
        $this->dryRun = (bool) $this->option('dry-run');
        $this->publishNow = (bool) $this->option('publish-now');
        $this->preventDuplicateReviewers = $this->resolvePreventDuplicateReviewers();
        $this->batchId = (string) Str::uuid();

        $productId = $this->option('product');
        $categoryId = $this->option('category');
        $brandId = $this->option('brand');
        $all = (bool) $this->option('all');
        $minReviews = max(1, (int) $this->option('min'));
        $maxReviews = max($minReviews, (int) $this->option('max'));
        $skipExisting = (bool) $this->option('skip-existing');
        $reporter = GenerationRunReporter::fromOption($this->option('run-id'));

        if (!$productId && $this->normalizeProductIds($this->option('products')) === [] && !$categoryId && !$brandId && !$all) {
            $this->error('You must specify --product, --products, --category, --brand, or --all');
            return self::FAILURE;
        }

        try {
            $countryFilters = $this->normalizeCountryFilters($this->option('country'));
            $localeFilters = $this->normalizeLocaleFilters($this->option('locale'));
            [$this->photoReviewChanceNumerator, $this->photoReviewChanceDenominator] = $this->resolvePhotoReviewChance();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $productTotal = $this->countProducts(
            $productId,
            $this->option('products'),
            $categoryId,
            $brandId,
            $all,
            $this->option('review-count-max'),
            $this->option('limit')
        );
        if ($productTotal < 1) {
            $this->error('No products found for the given criteria.');
            return self::FAILURE;
        }
        $reporter->setTotal($productTotal, [
            'created_reviews' => 0,
            'skipped_products' => 0,
            'selection' => [
                'product' => $productId,
                'products' => $this->normalizeProductIds($this->option('products')),
                'category' => $categoryId,
                'brand' => $brandId,
                'all' => $all,
                'review_count_max' => $this->normalizeNullableInt($this->option('review-count-max')),
                'limit' => $this->normalizeNullableInt($this->option('limit')),
            ],
        ]);

        $botPools = $this->getBotPools($countryFilters, $localeFilters);
        if ($botPools->isEmpty()) {
            $this->error('No bot users found for the provided locale/country filters.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Found %d products and %d bot pools (%d bot profiles).',
            $productTotal,
            $botPools->count(),
            $botPools->sum(fn (array $pool) => $pool['bots']->count())
        ));

        if ($this->dryRun) {
            $this->warn('Dry run mode: no reviews will be saved.');
        } elseif ($this->publishNow) {
            $this->warn('Publish-now mode: generated reviews will be visible immediately.');
        } else {
            $this->info('Generated reviews will be scheduled for delayed publication.');
        }

        if ($this->photoReviewChanceNumerator > 0) {
            $this->info(sprintf(
                'Photo reviews enabled with chance %d/%d.',
                $this->photoReviewChanceNumerator,
                $this->photoReviewChanceDenominator
            ));
        }

        $promptPath = (string) ($this->option('prompt-path') ?: self::DEFAULT_PROMPT_PATH);
        if (!is_file($promptPath)) {
            $this->error("Prompt file not found: {$promptPath}");
            return self::FAILURE;
        }

        $this->promptTemplate = trim((string) file_get_contents($promptPath));
        if ($this->promptTemplate === '') {
            $this->error('Prompt file is empty.');
            return self::FAILURE;
        }

        $products = $this->getProductsLazy(
            $productId,
            $this->option('products'),
            $categoryId,
            $brandId,
            $all,
            $this->option('review-count-max'),
            $this->option('limit')
        );

        $progress = $this->output->createProgressBar($productTotal);
        $progress->start();

        $createdReviews = collect();
        $totalGenerated = 0;
        $photoReviews = 0;
        $skippedProducts = 0;
        $processedProducts = 0;

        $rewardContext = app(ReviewRewardContext::class);
        $rewardContext->skipRewards(true);

        try {
            foreach ($products as $product) {
                if ($skipExisting && $this->hasExistingBotReviews($product)) {
                    $skippedProducts++;
                    $processedProducts++;
                    $progress->advance();
                    $reporter->setProgress($processedProducts, null, [
                        'created_reviews' => $totalGenerated,
                        'skipped_products' => $skippedProducts,
                    ]);
                    continue;
                }

                $desiredCount = random_int($minReviews, $maxReviews);
                $usedOwnerIds = $this->resolveReservedReviewerOwnerIds($product);
                $pool = $this->pickPoolForProduct($botPools, $usedOwnerIds, $desiredCount);

                if ($pool === null) {
                    $processedProducts++;
                    $progress->advance();
                    $reporter->setProgress($processedProducts, null, [
                        'created_reviews' => $totalGenerated,
                        'skipped_products' => $skippedProducts,
                    ]);
                    continue;
                }

                $availableBots = $pool['available_bots'];
                $reviewCount = min($desiredCount, $availableBots->count());
                if ($reviewCount < 1) {
                    $processedProducts++;
                    $progress->advance();
                    $reporter->setProgress($processedProducts, null, [
                        'created_reviews' => $totalGenerated,
                        'skipped_products' => $skippedProducts,
                    ]);
                    continue;
                }

                $productBots = $availableBots->shuffle()->take($reviewCount)->values();
                $ratings = array_fill(0, $productBots->count(), 5);
                $reviews = $this->generateBatchReviews(
                    $product,
                    $productBots,
                    $ratings,
                    $pool['locale'],
                    $pool['country']
                );

                if ($reviews === []) {
                    $this->newLine();
                    $this->warn(sprintf('Failed to generate reviews for product #%d', $product->id));
                    $processedProducts++;
                    $progress->advance();
                    $reporter->setProgress($processedProducts, null, [
                        'created_reviews' => $totalGenerated,
                        'skipped_products' => $skippedProducts,
                    ]);
                    continue;
                }

                $createdForProduct = collect();
                $botsArray = $productBots->all();
                $ownerIdsUsedInBatch = [];

                foreach ($reviews as $index => $reviewData) {
                    $userIndex = isset($reviewData['user_index']) ? (int) $reviewData['user_index'] : $index;
                    if (!isset($botsArray[$userIndex])) {
                        continue;
                    }

                    $bot = $botsArray[$userIndex];
                    $ownerId = $this->resolveBotOwnerId($bot);

                    if ($ownerId === null) {
                        continue;
                    }

                    if (in_array($ownerId, $usedOwnerIds, true) || in_array($ownerId, $ownerIdsUsedInBatch, true)) {
                        continue;
                    }

                    if ($this->dryRun) {
                        $this->newLine();
                        $this->line(sprintf(
                            '<fg=yellow>[DRY]</> <fg=cyan>%s</> | <fg=green>%s</> | <fg=magenta>%d★</> | [%s/%s] %s',
                            mb_substr($this->resolveProductName($product, $pool['locale']), 0, 25),
                            $bot->fullname ?? 'Bot',
                            5,
                            $pool['country'],
                            $pool['locale'],
                            mb_substr((string) ($reviewData['text'] ?? ''), 0, 80)
                        ));
                        $ownerIdsUsedInBatch[] = $ownerId;
                        $totalGenerated++;
                        continue;
                    }

                    $review = $this->createReview($product, $bot, $reviewData, 5);
                    if ($review) {
                        if ($this->shouldAttachPhotoReview() && $generatedProductPhotoReviewService->attachFirstApprovedPhoto($review, $product)) {
                            $photoReviews++;
                        }

                        $createdForProduct->push($review);
                        $ownerIdsUsedInBatch[] = $ownerId;
                        $totalGenerated++;
                    }
                }

                $createdReviews = $createdReviews->concat($createdForProduct);
                $processedProducts++;
                $progress->advance();
                $reporter->setProgress($processedProducts, null, [
                    'created_reviews' => $totalGenerated,
                    'skipped_products' => $skippedProducts,
                ]);
            }
        } finally {
            $rewardContext->skipRewards(false);
        }

        $scheduledCount = 0;
        if (!$this->dryRun && !$this->publishNow && $createdReviews->isNotEmpty()) {
            $scheduledCount = $reviewScheduleService->schedule(
                $createdReviews,
                $this->resolveScheduleStart(),
                $this->resolveScheduleMinPerDay(),
                $this->resolveScheduleMaxPerDay(),
                $this->resolveScheduleHourFrom(),
                $this->resolveScheduleHourTo()
            );
        }

        $progress->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '✓ Generated %d reviews for %d products.',
            $totalGenerated,
            $productTotal - $skippedProducts
        ));

        if ($skippedProducts > 0) {
            $this->info(sprintf('○ Skipped %d products with existing bot reviews.', $skippedProducts));
        }

        if (!$this->dryRun && !$this->publishNow) {
            $this->info(sprintf('○ Scheduled %d reviews for delayed publication.', $scheduledCount));
        }
        if (!$this->dryRun && $photoReviews > 0) {
            $this->info(sprintf('○ Attached photos to %d reviews.', $photoReviews));
        }
        $reporter->merge([
            'created_reviews' => $totalGenerated,
            'photo_reviews' => $photoReviews,
            'skipped_products' => $skippedProducts,
            'scheduled_reviews' => $scheduledCount,
        ], [
            'created_reviews' => $totalGenerated,
            'photo_reviews' => $photoReviews,
            'skipped_products' => $skippedProducts,
            'scheduled_reviews' => $scheduledCount,
            'dry_run' => $this->dryRun,
            'publish_now' => $this->publishNow,
        ]);

        return self::SUCCESS;
    }

    protected function generateBatchReviews(
        Product $product,
        Collection $bots,
        array $ratings,
        string $locale,
        string $country
    ): array {
        $reviewBriefs = $this->buildReviewBriefs($bots->count());

        $usersData = $bots->map(function (Profile $bot, int $index) use ($ratings, $locale, $country, $reviewBriefs) {
            $botData = $bot->rolePayload('bot');

            return [
                'index' => $index,
                'name' => $bot->fullname ?? $bot->first_name ?? ($bot->user?->name ?? 'User'),
                'locale' => $this->resolveBotLocale($bot),
                'country' => $this->resolveBotCountry($bot),
                'age' => $botData['age'] ?? $this->ageFromProfile($bot),
                'gender' => $botData['gender'] ?? 'unknown',
                'character' => $botData['character'] ?? '',
                'speech_style' => $botData['speech_style'] ?? '',
                'emoji_usage' => $botData['emoji_usage'] ?? 'minimal',
                'literacy_level' => $botData['literacy_level'] ?? 7,
                'message_length' => $botData['message_length'] ?? 'short',
                'punctuation_usage' => $botData['punctuation_usage'] ?? '',
                'rating' => $ratings[$index] ?? 5,
                'review_brief' => $reviewBriefs[$index] ?? null,
            ];
        })->values()->all();

        $productContext = [
            'name' => $this->resolveProductName($product, $locale),
            'category' => $this->resolveProductCategories($product, $locale),
            'description' => $this->resolveProductDescription($product, $locale, $country),
        ];

        $prompt = str_replace([
            '{{PRODUCT_NAME}}',
            '{{PRODUCT_CATEGORY}}',
            '{{PRODUCT_DESCRIPTION}}',
            '{{LANGUAGE}}',
            '{{COUNTRY}}',
            '{{USERS_DATA}}',
            '{{COUNT}}',
        ], [
            $productContext['name'],
            $productContext['category'],
            $productContext['description'],
            $this->resolveLanguageName($locale),
            $country,
            json_encode($usersData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            (string) count($usersData),
        ], $this->promptTemplate);

        $payload = [
            'prompt' => $prompt,
            'response_format' => 'array',
            'output_type' => 'collection',
            'quantity' => count($usersData),
        ];

        if ($driver = $this->option('driver')) {
            $payload['driver'] = $driver;
        }
        if ($model = $this->option('model')) {
            $payload['model'] = $model;
        }
        if (($temp = $this->option('temperature')) !== null) {
            $payload['temperature'] = (float) $temp;
        }
        if (($maxTokens = $this->option('max-tokens')) !== null) {
            $payload['max_tokens'] = (int) $maxTokens;
        }
        if ($this->option('force')) {
            $payload['force'] = true;
        }

        try {
            $response = $this->generator->generate($payload);
            $result = $response->result;

            if (!is_array($result)) {
                return [];
            }

            if (isset($result['text'])) {
                $result = [$result];
            }

            return $this->styleGeneratedReviews(array_values($result), $reviewBriefs, $locale);
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('AI generation failed: ' . $exception->getMessage());
            return [];
        }
    }

    protected function getProducts(
        ?string $productId,
        mixed $productIds,
        ?string $categoryId,
        ?string $brandId,
        bool $all,
        mixed $reviewCountMax,
        mixed $limit
    ): Collection
    {
        return collect($this->getProductsLazy(
            $productId,
            $productIds,
            $categoryId,
            $brandId,
            $all,
            $reviewCountMax,
            $limit
        )->all());
    }

    protected function countProducts(
        ?string $productId,
        mixed $productIds,
        ?string $categoryId,
        ?string $brandId,
        bool $all,
        mixed $reviewCountMax,
        mixed $limit
    ): int {
        $canonicalIdsQuery = $this->limitCanonicalProductIdsQuery(
            $this->buildCanonicalProductIdsQuery(
                $productId,
                $productIds,
                $categoryId,
                $brandId,
                $all,
                $reviewCountMax
            ),
            $limit
        );

        return (int) DB::query()
            ->fromSub($canonicalIdsQuery->toBase(), 'selected_products')
            ->count();
    }

    protected function getProductsLazy(
        ?string $productId,
        mixed $productIds,
        ?string $categoryId,
        ?string $brandId,
        bool $all,
        mixed $reviewCountMax,
        mixed $limit
    ): LazyCollection {
        $canonicalIdsQuery = $this->limitCanonicalProductIdsQuery(
            $this->buildCanonicalProductIdsQuery(
                $productId,
                $productIds,
                $categoryId,
                $brandId,
                $all,
                $reviewCountMax
            ),
            $limit
        );

        return $canonicalIdsQuery
            ->toBase()
            ->cursor()
            ->map(fn ($row) => (int) $row->canonical_id)
            ->chunk(50)
            ->flatMap(function ($idChunk): Collection {
                $canonicalIds = collect($idChunk)
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0)
                    ->values();

                if ($canonicalIds->isEmpty()) {
                    return collect();
                }

                $products = Product::query()
                    ->without(['ap', 'suppliers'])
                    ->with(['categories', 'regionalContents', 'parent'])
                    ->whereIn('id', $canonicalIds->all())
                    ->get()
                    ->keyBy(fn (Product $product) => (int) $product->getKey());

                return $canonicalIds
                    ->map(fn (int $id) => $products->get($id))
                    ->filter(fn ($product) => $product instanceof Product)
                    ->values();
            });
    }

    protected function buildProductSelectionQuery(
        ?string $productId,
        mixed $productIds,
        ?string $categoryId,
        ?string $brandId,
        bool $all,
        mixed $reviewCountMax
    ): Builder {
        $query = Product::query()
            ->without(['categories', 'ap', 'suppliers', 'parent'])
            ->where('is_active', true);

        if ($productId) {
            return $query->where('id', $productId);
        }

        $productIds = $this->normalizeProductIds($productIds);
        if ($productIds !== []) {
            return $query->whereIn('id', $productIds);
        }

        if ($categoryId) {
            $categoryIds = Category::getCategoryNodeIdList(null, (int) $categoryId);
            if (empty($categoryIds)) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereHas('categories', fn ($q) => $q->whereIn('ak_product_categories.id', $categoryIds));
        } elseif ($brandId) {
            if (! Brand::query()->whereKey((int) $brandId)->exists()) {
                return $query->whereRaw('1 = 0');
            }

            $query->where('brand_id', (int) $brandId);
        } elseif (! $all) {
            return $query->whereRaw('1 = 0');
        }

        $reviewCountMax = $this->normalizeNullableInt($reviewCountMax);
        if ($reviewCountMax !== null) {
            $query->withCount([
                'reviews as moderated_reviews_count' => fn ($reviewQuery) => $reviewQuery->moderated(),
            ])->having('moderated_reviews_count', '<=', $reviewCountMax);
        }

        return $query;
    }

    protected function buildCanonicalProductIdsQuery(
        ?string $productId,
        mixed $productIds,
        ?string $categoryId,
        ?string $brandId,
        bool $all,
        mixed $reviewCountMax
    ): Builder {
        $table = (new Product())->getTable();
        $query = $this->buildProductSelectionQuery(
            $productId,
            $productIds,
            $categoryId,
            $brandId,
            $all,
            $reviewCountMax
        );

        return $query
            ->selectRaw(
                "CASE WHEN {$table}.parent_id IS NULL OR {$table}.parent_id = 0 THEN {$table}.id ELSE {$table}.parent_id END as canonical_id"
            )
            ->distinct()
            ->orderBy('canonical_id');
    }

    protected function limitCanonicalProductIdsQuery(Builder $query, mixed $limit): Builder
    {
        $limit = $this->normalizeNullableInt($limit);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query;
    }

    protected function getBotPools(array $countryFilters, array $localeFilters): Collection
    {
        $query = Profile::query()
            ->where('role', 'bot')
            ->whereNotNull('user_id')
            ->with('user');

        if ($countryFilters !== []) {
            $query->whereIn('country_code', $countryFilters);
        }

        if ($localeFilters !== []) {
            $query->whereIn('locale', $localeFilters);
        }

        return $query->get()
            ->map(function (Profile $bot): ?array {
                $locale = $this->resolveBotLocale($bot);
                if ($locale === null) {
                    return null;
                }

                return [
                    'key' => $this->poolKey($this->resolveBotCountry($bot), $locale),
                    'country' => $this->resolveBotCountry($bot),
                    'locale' => $locale,
                    'language' => $this->resolveLanguageName($locale),
                    'bot' => $bot,
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(function (Collection $items, string $key): array {
                $first = $items->first();

                return [
                    'key' => $key,
                    'country' => $first['country'],
                    'locale' => $first['locale'],
                    'language' => $first['language'],
                    'bots' => $items->pluck('bot')->values(),
                ];
            })
            ->values();
    }

    protected function pickPoolForProduct(Collection $botPools, array $usedOwnerIds, int $desiredCount): ?array
    {
        $candidates = $botPools->map(function (array $pool) use ($usedOwnerIds) {
            $availableBots = $pool['bots']
                ->filter(fn (Profile $bot) => !in_array($this->resolveBotOwnerId($bot), $usedOwnerIds, true))
                ->values();

            $pool['available_bots'] = $availableBots;
            $pool['available_count'] = $availableBots->count();

            return $pool;
        })->filter(fn (array $pool) => $pool['available_count'] > 0)->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $preferred = $candidates->filter(fn (array $pool) => $pool['available_count'] >= $desiredCount);
        if ($preferred->isNotEmpty()) {
            $candidates = $preferred;
        }

        return $candidates->shuffle()->first();
    }

    protected function hasExistingBotReviews(Product $product): bool
    {
        $familyProductIds = $this->productTargetResolver->familyProductIds($product);

        return Review::query()
            ->where('reviewable_type', Product::class)
            ->whereIn('reviewable_id', $familyProductIds)
            ->where(fn ($q) => $q
                ->whereJsonContains('extras->generated_by_bot', true)
                ->orWhereJsonContains('extras->skip_reward', true)
            )
            ->exists();
    }

    protected function getExistingReviewerOwnerIds(Product $product): array
    {
        $familyProductIds = $this->productTargetResolver->familyProductIds($product);

        return Review::query()
            ->where('reviewable_type', Product::class)
            ->whereIn('reviewable_id', $familyProductIds)
            ->where(fn ($query) => $query->whereNull('parent_id')->orWhere('parent_id', 0))
            ->whereNotNull('owner_id')
            ->pluck('owner_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resolveReservedReviewerOwnerIds(Product $product): array
    {
        if (! $this->preventDuplicateReviewers) {
            return [];
        }

        return $this->getExistingReviewerOwnerIds($product);
    }

    protected function createReview(Product $product, Profile $bot, array $reviewData, int $rating): ?Review
    {
        $canonicalProductId = $this->productTargetResolver->canonicalProductId($product);
        $ownerId = $this->resolveBotOwnerId($bot);
        if ($ownerId === null) {
            return null;
        }

        if ($this->preventDuplicateReviewers && $this->reviewAlreadyExists($product, $ownerId)) {
            return null;
        }

        $extras = [
            'verified_purchase' => '1',
            'skip_reward' => true,
            'generated_by_bot' => true,
            'generated_batch_id' => $this->batchId,
            'bot_profile_id' => $bot->id,
        ];

        if (!empty($reviewData['advantages'])) {
            $advantages = $reviewData['advantages'];
            $extras['advantages'] = is_array($advantages) ? implode("\n", $advantages) : (string) $advantages;
        }

        if (!empty($reviewData['flaws'])) {
            $flaws = $reviewData['flaws'];
            $extras['flaws'] = is_array($flaws) ? implode("\n", $flaws) : (string) $flaws;
        }

        $extras['owner'] = [
            'id' => $ownerId,
            'name' => $bot->fullname ?? $bot->user?->name,
            'first_name' => $bot->first_name,
            'last_name' => $bot->last_name,
            'photo' => $bot->avatarUrl(),
        ];

        try {
            $review = new Review();
            $review->text = trim((string) ($reviewData['text'] ?? ''));
            $review->rating = $rating;
            $review->owner_id = $ownerId;
            $review->reviewable_type = Product::class;
            $review->reviewable_id = $canonicalProductId;
            $review->is_moderated = $this->publishNow;
            $review->lang = $this->resolveBotLocale($bot);
            $review->country = $this->resolveBotCountry($bot);
            $review->extras = $extras;
            $review->save();

            return $review;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('Failed to create review: ' . $exception->getMessage());
            return null;
        }
    }

    protected function shouldAttachPhotoReview(): bool
    {
        if ($this->photoReviewChanceNumerator < 1 || $this->photoReviewChanceDenominator < 1) {
            return false;
        }

        return random_int(1, $this->photoReviewChanceDenominator) <= $this->photoReviewChanceNumerator;
    }

    protected function resolveScheduleStart(): Carbon
    {
        $value = trim((string) $this->option('schedule-start'));

        try {
            $start = $value !== '' ? Carbon::parse($value) : now()->copy()->addDay();
        } catch (\Throwable) {
            $start = now()->copy()->addDay();
        }

        if ($start->lessThan(now())) {
            $start = now()->copy()->addMinutes(10);
        }

        return $start->copy();
    }

    protected function resolveScheduleMinPerDay(): int
    {
        return max(1, (int) $this->option('schedule-min-per-day'));
    }

    protected function resolveScheduleMaxPerDay(): int
    {
        return max($this->resolveScheduleMinPerDay(), (int) $this->option('schedule-max-per-day'));
    }

    protected function resolveScheduleHourFrom(): int
    {
        return max(0, min(23, (int) $this->option('schedule-hour-from')));
    }

    protected function resolveScheduleHourTo(): int
    {
        return max($this->resolveScheduleHourFrom(), min(23, (int) $this->option('schedule-hour-to')));
    }

    protected function resolvePhotoReviewChance(): array
    {
        $value = trim((string) $this->option('photo-review-chance'));
        if ($value === '') {
            return [0, 10];
        }

        if (!preg_match('/^\s*(\d+)\s*\/\s*(\d+)\s*$/', $value, $matches)) {
            throw new \InvalidArgumentException('Photo review chance must be in the format X/Y.');
        }

        $numerator = (int) $matches[1];
        $denominator = (int) $matches[2];

        if ($denominator < 1) {
            throw new \InvalidArgumentException('Photo review chance denominator must be greater than zero.');
        }

        if ($numerator < 0 || $numerator > $denominator) {
            throw new \InvalidArgumentException('Photo review chance numerator must be between 0 and the denominator.');
        }

        return [$numerator, $denominator];
    }

    protected function resolvePreventDuplicateReviewers(): bool
    {
        return $this->normalizeBooleanOption($this->option('prevent-duplicate-reviewers'), true);
    }

    protected function reviewAlreadyExists(Product $product, int $ownerId): bool
    {
        $familyProductIds = $this->productTargetResolver->familyProductIds($product);

        return Review::query()
            ->where('reviewable_type', Product::class)
            ->whereIn('reviewable_id', $familyProductIds)
            ->where('owner_id', $ownerId)
            ->where(fn ($query) => $query->whereNull('parent_id')->orWhere('parent_id', 0))
            ->exists();
    }

    protected function resolveBotOwnerId(Profile $bot): ?int
    {
        $ownerId = $bot->user_id ?? null;

        return $ownerId ? (int) $ownerId : null;
    }

    protected function resolveBotLocale(Profile $bot): ?string
    {
        $locale = strtolower(trim((string) ($bot->locale ?? '')));
        if ($locale === '') {
            return null;
        }

        return self::LANGUAGE_ALIASES[$locale] ?? $locale;
    }

    protected function normalizeBooleanOption(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return $default;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    protected function normalizeProductIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($values as $entry) {
            foreach (explode(',', (string) $entry) as $part) {
                $part = trim($part);
                if ($part === '' || ! ctype_digit($part)) {
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

    protected function resolveBotCountry(Profile $bot): string
    {
        $country = strtoupper(trim((string) ($bot->country_code ?? '')));

        return $country !== '' ? $country : strtoupper((string) config('backpack.reviews.global_country_code', 'ZZ'));
    }

    protected function resolveProductName(Product $product, string $locale): string
    {
        $value = method_exists($product, 'getTranslation')
            ? $product->getTranslation('name', $locale, false)
            : ($product->name ?? null);

        $normalized = $this->normalizeTextValue($value);

        return $normalized ?: ('Product #' . $product->id);
    }

    protected function resolveProductCategories(Product $product, string $locale): string
    {
        $categories = $product->categories ?? collect();

        $names = collect($categories)->map(function ($category) use ($locale) {
            if (method_exists($category, 'getTranslation')) {
                return $this->normalizeTextValue($category->getTranslation('name', $locale, false));
            }

            return $this->normalizeTextValue($category->name ?? null);
        })->filter()->unique()->values();

        return $names->isNotEmpty() ? $names->implode(', ') : 'General product';
    }

    protected function resolveProductDescription(Product $product, string $locale, string $country): string
    {
        $candidates = [];

        if (method_exists($product, 'getRegionalContentValue')) {
            $candidates[] = $product->getRegionalContentValue('excerpt', $country, $locale, true);
            $candidates[] = $product->getRegionalContentValue('content', $country, $locale, true);
        }

        if (method_exists($product, 'getTranslation')) {
            $candidates[] = $product->getTranslation('excerpt', $locale, true);
            $candidates[] = $product->getTranslation('content', $locale, true);
        }

        $candidates[] = $product->excerpt ?? null;
        $candidates[] = $product->content ?? null;

        $parts = [];
        foreach ($candidates as $candidate) {
            $value = $this->normalizeTextValue($candidate);
            if ($value !== null) {
                $parts[] = $value;
            }
        }

        $parts = collect($parts)
            ->unique(fn (string $value) => mb_strtolower($value))
            ->values();

        if ($parts->isEmpty()) {
            return 'No product description provided.';
        }

        return Str::limit($parts->implode("\n\n"), 2200, '...');
    }

    protected function normalizeTextValue(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $normalized = $this->normalizeTextValue($item);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\s*br\s*\/?>/iu', ' ', $value) ?? $value;
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value !== '' ? $value : null;
    }

    protected function buildReviewBriefs(int $count): array
    {
        $angles = collect(self::REVIEW_BRIEF_ANGLES)->shuffle()->values();
        $structures = collect(self::REVIEW_BRIEF_STRUCTURES)->shuffle()->values();
        $lengths = collect(self::REVIEW_BRIEF_LENGTHS)->shuffle()->values();
        $emojiPolicies = collect([
            'no emoji',
            'no emoji',
            'at most one emoji at the end',
            'one emoji only if this user naturally uses emoji',
            'text emoticon allowed, no emoji',
            'emoji allowed only inside a very short review',
        ])->shuffle()->values();

        $briefs = [];

        for ($index = 0; $index < $count; $index++) {
            $briefs[] = [
                'angle' => $angles[$index % $angles->count()],
                'structure' => $structures[$index % $structures->count()],
                'target_length' => $lengths[$index % $lengths->count()],
                'emoji_policy' => $emojiPolicies[$index % $emojiPolicies->count()],
                'style_mode' => self::REVIEW_STYLE_MODES[$index % count(self::REVIEW_STYLE_MODES)],
                'postprocess_required' => true,
                'product_name_policy' => $index % 5 === 0
                    ? 'may describe the product naturally without exact SKU/code'
                    : 'do not mention the product name',
                'noise_policy' => [
                    'one_word' => 'replace with a one or two word mobile review',
                    'tiny_broken' => 'broken grammar, missing commas, short phone typing',
                    'emoji_spam' => 'allow several emojis or text emoticons in a row',
                    'sloppy_lowercase' => 'lowercase, loose punctuation, casual spelling',
                    'camel_case' => 'one compact camelCase-ish phrase is allowed',
                    'punctuation_spam' => 'spam punctuation signs like !!!, ??, ...',
                    'typo_heavy' => 'allow visible typos, missing diacritics, rushed typing',
                ][self::REVIEW_STYLE_MODES[$index % count(self::REVIEW_STYLE_MODES)]] ?? 'natural review',
                'avoid_patterns' => [
                    'do not use the same sentence rhythm as other users',
                    'do not use a repeated contrast formula like "good, but ..." unless the angle explicitly requires a minor flaw',
                    'do not put one emoji between every sentence',
                ],
            ];
        }

        return $briefs;
    }

    protected function styleGeneratedReviews(array $reviews, array $reviewBriefs, string $locale): array
    {
        return collect($reviews)
            ->map(function (mixed $review, int $index) use ($reviewBriefs, $locale): array {
                $review = is_array($review) ? $review : ['text' => (string) $review];
                $userIndex = isset($review['user_index']) ? (int) $review['user_index'] : $index;
                $brief = $reviewBriefs[$userIndex] ?? $reviewBriefs[$index] ?? [];
                $styleMode = (string) ($brief['style_mode'] ?? 'plain_medium');
                $text = trim((string) ($review['text'] ?? ''));

                $review['text'] = $this->applyReviewStyleMode($text, $styleMode, $locale, $userIndex);

                if (in_array($styleMode, ['one_word', 'tiny_broken', 'emoji_spam', 'sloppy_lowercase', 'camel_case'], true)) {
                    $review['advantages'] = [];
                    $review['flaws'] = [];
                }

                return $review;
            })
            ->values()
            ->all();
    }

    protected function applyReviewStyleMode(string $text, string $styleMode, string $locale, int $index): string
    {
        $text = $this->normalizeReviewText($text);

        return match ($styleMode) {
            'one_word' => $this->pickShortReviewPhrase($locale, 'one_word', $index),
            'tiny_broken' => $this->pickShortReviewPhrase($locale, 'tiny_broken', $index),
            'emoji_spam' => $this->appendEmojiSpam($this->shortenReviewText($text, 16, $locale, $index), $index),
            'sloppy_lowercase' => $this->makeSloppyLowercase($this->shortenReviewText($text, 22, $locale, $index), $index),
            'camel_case' => $this->makeCamelCaseReview($text, $locale, $index),
            'punctuation_spam' => $this->makePunctuationSpam($this->shortenReviewText($text, 28, $locale, $index), $index),
            'typo_heavy' => $this->makeTypoHeavy($this->shortenReviewText($text, 18, $locale, $index), $locale),
            'clean_short' => $this->shortenReviewText($text, 10, $locale, $index),
            'longer_story' => $this->ensureLongerStory($text, $locale, $index),
            default => $this->shortenReviewText($text, 34, $locale, $index),
        };
    }

    protected function normalizeReviewText(string $text): string
    {
        $text = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text !== '' ? $text : 'OK';
    }

    protected function shortenReviewText(string $text, int $maxWords, string $locale, int $index): string
    {
        $words = preg_split('/\s+/u', $this->normalizeReviewText($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) <= $maxWords) {
            return $this->normalizeReviewText($text);
        }

        $short = implode(' ', array_slice($words, 0, max(1, $maxWords)));
        $short = preg_replace('/[,;:]$/u', '', $short) ?? $short;

        if (!preg_match('/[.!?…]$/u', $short)) {
            $short .= $index % 3 === 0 ? '...' : '.';
        }

        return $short;
    }

    protected function pickShortReviewPhrase(string $locale, string $styleMode, int $index): string
    {
        $locale = strtolower($locale);
        $phrases = [
            'cs' => [
                'one_word' => ['Top', 'Super', 'Spokojenost', 'Diky!', 'Fajn', 'Sedlo mi'],
                'tiny_broken' => ['prislo rychle dobry!!', 'za me ok dik', 'fungujeee 😅', 'jo dobry...', 'beru zas'],
            ],
            'uk' => [
                'one_word' => ['Клас', 'Супер', 'Дякую!', 'Зайшло', 'Ок'],
                'tiny_broken' => ['норм зайшло!!', 'дякую все ок', 'буду брати ще', 'ефект є))', 'топчик 😅'],
            ],
            'ru' => [
                'one_word' => ['Класс', 'Ок', 'Спасибо!', 'Зашло', 'Норм'],
                'tiny_broken' => ['норм зашло!!', 'спс все ок', 'буду брать еще', 'эфект есть))', 'топчик 😅'],
            ],
            'en' => [
                'one_word' => ['Nice', 'Thanks!', 'Good', 'Works', 'Liked it'],
                'tiny_broken' => ['came fast good!!', 'works for me', 'thx all ok', 'will buy again', 'yep nice 😅'],
            ],
            'de' => [
                'one_word' => ['Gut', 'Danke!', 'Passt', 'Top', 'Gerne wieder'],
                'tiny_broken' => ['kam schnell gut!!', 'passt fur mich', 'danke alles ok', 'nehm ich wieder', 'jo gut 😅'],
            ],
            'es' => [
                'one_word' => ['Bien', 'Gracias!', 'Genial', 'Me gusto', 'Todo ok'],
                'tiny_broken' => ['llego rapido bien!!', 'para mi ok', 'gracias todo bien', 'repito seguro', 'si va 😅'],
            ],
        ];

        $pool = $phrases[$locale][$styleMode] ?? $phrases['en'][$styleMode] ?? ['OK'];

        return $pool[$index % count($pool)];
    }

    protected function appendEmojiSpam(string $text, int $index): string
    {
        $sets = [' 😍😍', ' 👍🔥🔥', ' :D :D', ' 😅✨', ' 💚💚💚', ')))))'];
        $text = rtrim($text, '. ');

        return $text . ($sets[$index % count($sets)]);
    }

    protected function makeSloppyLowercase(string $text, int $index): string
    {
        $text = mb_strtolower($text);
        $text = str_replace([',', ';'], '', $text);
        $text = preg_replace('/\s+\.\s*/u', '... ', $text) ?? $text;
        $text = rtrim($text, '.!? ');

        return $text . ($index % 2 === 0 ? '...' : '!!');
    }

    protected function makeCamelCaseReview(string $text, string $locale, int $index): string
    {
        $base = $this->pickShortReviewPhrase($locale, 'tiny_broken', $index);
        $words = preg_split('/\s+/u', preg_replace('/[^\pL\pN\s]+/u', ' ', $base) ?? $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) < 2) {
            $words = preg_split('/\s+/u', $this->shortenReviewText($text, 5, $locale, $index), -1, PREG_SPLIT_NO_EMPTY) ?: $words;
        }

        $camel = '';
        foreach (array_slice($words, 0, 4) as $position => $word) {
            $word = mb_strtolower($word);
            $camel .= $position === 0 ? $word : mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        return $camel !== '' ? $camel . '!!' : 'allOk!!';
    }

    protected function makePunctuationSpam(string $text, int $index): string
    {
        $suffixes = ['!!!', '??))', '...!!!', '!!', ')))'];
        $text = rtrim($text, '.!? ');

        return $text . $suffixes[$index % count($suffixes)];
    }

    protected function makeTypoHeavy(string $text, string $locale): string
    {
        $text = $this->stripCommonDiacritics(mb_strtolower($text));
        $text = str_replace([',', ';', ':'], '', $text);
        $text = preg_replace('/\b(ch|sh|th)\b/iu', '$1h', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $locale = strtolower($locale);
        if (in_array($locale, ['cs', 'uk', 'ru'], true)) {
            $text = str_replace(['эффект', 'ефект', 'efekt'], ['эфект', 'еффект', 'efekt'], $text);
        }

        return rtrim($text, '.!? ') . '))';
    }

    protected function ensureLongerStory(string $text, string $locale, int $index): string
    {
        $text = $this->normalizeReviewText($text);

        if (str_word_count(strip_tags($text)) >= 28) {
            return $text;
        }

        $tails = [
            'cs' => ' Vzal jsem to spis na vecer po praci a presne tak mi to sedi.',
            'uk' => ' Брав для вечора після роботи, саме такий спокійний формат мені і підійшов.',
            'ru' => ' Брал на вечер после работы, именно такой спокойный формат мне и подошел.',
            'en' => ' I mostly use it in the evening after work, and that is exactly where it fits.',
            'de' => ' Ich nehme es eher abends nach der Arbeit, genau da passt es fur mich.',
            'es' => ' Lo uso mas por la noche despues del trabajo, ahi me encaja perfecto.',
        ];

        return $text . ($tails[strtolower($locale)] ?? $tails['en']);
    }

    protected function stripCommonDiacritics(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
            'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
            'ů' => 'u', 'ý' => 'y', 'ž' => 'z', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
            'ß' => 'ss', 'ї' => 'i', 'і' => 'i', 'є' => 'e', 'ґ' => 'g',
        ]);
    }

    protected function resolveLanguageName(string $locale): string
    {
        return self::LANGUAGE_MAP[$locale] ?? strtoupper($locale);
    }

    protected function normalizeCountryFilters(mixed $value): array
    {
        $items = $this->splitOptionValues($value, true);
        if ($items === []) {
            return [];
        }

        foreach ($items as $item) {
            if (!preg_match('/^[A-Z]{2}$/', $item)) {
                throw new \InvalidArgumentException(sprintf('Unsupported country code: %s', $item));
            }
        }

        return array_values(array_unique($items));
    }

    protected function normalizeLocaleFilters(mixed $value): array
    {
        $items = $this->splitOptionValues($value, false);
        if ($items === []) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $locale = self::LANGUAGE_ALIASES[$item] ?? $item;
            if (!preg_match('/^[a-z]{2,8}$/', $locale)) {
                throw new \InvalidArgumentException(sprintf('Unsupported locale: %s', $item));
            }

            $normalized[] = $locale;
        }

        return array_values(array_unique($normalized));
    }

    protected function splitOptionValues(mixed $value, bool $upper = false): array
    {
        $values = is_array($value) ? $value : (is_string($value) ? [$value] : []);
        $items = [];

        foreach ($values as $entry) {
            foreach (explode(',', (string) $entry) as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    continue;
                }

                $items[] = $upper ? strtoupper($part) : strtolower($part);
            }
        }

        return array_values(array_unique($items));
    }

    protected function poolKey(string $country, string $locale): string
    {
        return $country . ':' . $locale;
    }

    protected function ageFromProfile(Profile $bot): ?int
    {
        if (!$bot->birthdate) {
            return null;
        }

        return Carbon::parse($bot->birthdate)->age;
    }
}
