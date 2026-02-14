<?php

namespace Backpack\Reviews\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;
use Backpack\Reviews\app\Models\Review;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Profile\app\Models\Profile;
use App\Support\ReviewRewardContext;

class GenerateProductReviews extends Command
{
    protected $signature = 'reviews:generate
        {--product= : Product ID to generate reviews for}
        {--category= : Category ID to generate reviews for all products in it}
        {--all : Generate reviews for all products}
        {--min=5 : Minimum number of reviews per product}
        {--max=20 : Maximum number of reviews per product}
        {--country=UA : Country code (UA, CZ, etc.)}
        {--locale=uk : Locale/language code (uk, cs, en, ru)}
        {--driver= : AI driver name}
        {--model= : AI model name}
        {--temperature=0.9 : Model temperature (higher = more variety)}
        {--max-tokens= : Max tokens for the response}
        {--force : Force provider even if marked unavailable}
        {--prompt-path= : Path to prompt template file}
        {--skip-existing : Skip products that already have bot reviews}
        {--dry-run : Do not write to the database}';

    protected $description = 'Generate product reviews using AI from bot users (batch mode)';

    private const DEFAULT_PROMPT_PATH = __DIR__ . '/../../../resources/prompts/review-generator.txt';
    private const LANGUAGE_MAP = [
        'uk' => 'Ukrainian',
        'ua' => 'Ukrainian',
        'cs' => 'Czech',
        'en' => 'English',
        'ru' => 'Russian',
        'de' => 'German',
        'es' => 'Spanish',
    ];

    protected ContentGenerator $generator;
    protected string $promptTemplate;
    protected string $countryCode;
    protected string $locale;
    protected string $language;
    protected bool $dryRun;

    public function handle(ContentGenerator $generator): int
    {
        $this->generator = $generator;
        
        $productId = $this->option('product');
        $categoryId = $this->option('category');
        $all = $this->option('all');
        $minReviews = max(1, (int) $this->option('min'));
        $maxReviews = max($minReviews, (int) $this->option('max'));
        $this->countryCode = strtoupper(trim($this->option('country')));
        $this->locale = strtolower(trim($this->option('locale')));
        $this->language = self::LANGUAGE_MAP[$this->locale] ?? 'English';
        $this->dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        if (!$productId && !$categoryId && !$all) {
            $this->error('You must specify --product, --category, or --all');
            return self::FAILURE;
        }

        $products = $this->getProducts($productId, $categoryId, $all);
        if ($products->isEmpty()) {
            $this->error('No products found for the given criteria.');
            return self::FAILURE;
        }

        $bots = $this->getBotUsers($this->countryCode, $this->locale);
        if ($bots->isEmpty()) {
            $this->error("No bot users found for country={$this->countryCode} and locale={$this->locale}");
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Found %d products and %d bot users (country=%s, locale=%s)',
            $products->count(),
            $bots->count(),
            $this->countryCode,
            $this->locale
        ));

        if ($this->dryRun) {
            $this->warn('Dry run mode: no reviews will be saved.');
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

        $progress = $this->output->createProgressBar($products->count());
        $progress->start();

        $totalCreated = 0;
        $skippedProducts = 0;

        $rewardContext = app(ReviewRewardContext::class);
        $rewardContext->skipRewards(true);

        try {
            foreach ($products as $product) {
                if ($skipExisting && $this->hasExistingBotReviews($product)) {
                    $skippedProducts++;
                    $progress->advance();
                    continue;
                }

                $reviewCount = rand($minReviews, $maxReviews);
                
                $usedBotIds = $this->getExistingReviewerIds($product);
                $availableBots = $bots->filter(fn($bot) => !in_array($bot->id, $usedBotIds));
                $productBots = $availableBots->shuffle()->take(min($reviewCount, $availableBots->count()));
                
                if ($productBots->isEmpty()) {
                    $progress->advance();
                    continue;
                }

                // Generate ratings for all bots
                $ratings = $productBots->map(fn() => $this->generateRating())->values()->all();
                
                // BATCH: Generate all reviews for this product in ONE request
                $reviews = $this->generateBatchReviews($product, $productBots->values(), $ratings);
                
                if (empty($reviews)) {
                    $this->newLine();
                    $this->warn(sprintf('Failed to generate reviews for product #%d', $product->id));
                    $progress->advance();
                    continue;
                }

                $createdForProduct = 0;
                $botsArray = $productBots->values()->all();
                
                foreach ($reviews as $index => $reviewData) {
                    $userIndex = $reviewData['user_index'] ?? $index;
                    if (!isset($botsArray[$userIndex])) {
                        continue;
                    }
                    
                    $bot = $botsArray[$userIndex];
                    $rating = $ratings[$userIndex] ?? 5;
                    $hasExtras = !empty($reviewData['advantages']) || !empty($reviewData['flaws']);

                    if ($this->dryRun) {
                        $this->newLine();
                        $this->line(sprintf(
                            '<fg=yellow>[DRY]</> <fg=cyan>%s</> | <fg=green>%s</> | <fg=magenta>%d★</> | %s',
                            mb_substr($product->name, 0, 25),
                            $bot->fullname ?? 'Bot',
                            $rating,
                            mb_substr($reviewData['text'] ?? '', 0, 60)
                        ));
                        $createdForProduct++;
                    } else {
                        $review = $this->createReview($product, $bot, $reviewData, $rating, $hasExtras);
                        if ($review) {
                            $createdForProduct++;
                        }
                    }
                }

                $totalCreated += $createdForProduct;
                $progress->advance();
            }
        } finally {
            $rewardContext->skipRewards(false);
        }

        $progress->finish();
        $this->newLine(2);
        
        $this->info(sprintf('✓ Generated %d reviews for %d products.', $totalCreated, $products->count() - $skippedProducts));
        
        if ($skippedProducts > 0) {
            $this->info(sprintf('○ Skipped %d products with existing bot reviews.', $skippedProducts));
        }

        return self::SUCCESS;
    }

    /**
     * Generate ALL reviews for a product in ONE AI request
     */
    protected function generateBatchReviews(Product $product, Collection $bots, array $ratings): array
    {
        $usersData = $bots->map(function ($bot, $index) use ($ratings) {
            $botData = $bot->rolePayload('bot');
            return [
                'index' => $index,
                'name' => $bot->fullname ?? $bot->first_name ?? 'User',
                'age' => $botData['age'] ?? rand(25, 45),
                'gender' => $botData['gender'] ?? 'unknown',
                'character' => $botData['character'] ?? '',
                'speech_style' => $botData['speech_style'] ?? '',
                'emoji_usage' => $botData['emoji_usage'] ?? 'minimal',
                'literacy_level' => $botData['literacy_level'] ?? 7,
                'message_length' => $botData['message_length'] ?? 'short',
                'punctuation' => $botData['punctuation_usage'] ?? '',
                'rating' => $ratings[$index] ?? 5,
            ];
        })->values()->all();

        $ratingsData = collect($ratings)->map(fn($r, $i) => "User $i: {$r}★")->implode(', ');
        
        $usersJson = json_encode($usersData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = str_replace([
            '{{PRODUCT_NAME}}',
            '{{PRODUCT_CATEGORY}}',
            '{{PRODUCT_DESCRIPTION}}',
            '{{LANGUAGE}}',
            '{{USERS_DATA}}',
            '{{RATINGS_DATA}}',
            '{{COUNT}}',
        ], [
            $product->name,
            $product->categories->pluck('name')->implode(', '),
            mb_substr(strip_tags($product->excerpt ?? $product->content ?? ''), 0, 300),
            $this->language,
            $usersJson,
            $ratingsData,
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

            // Normalize: ensure it's a list of review objects
            if (isset($result['text'])) {
                // Single review returned as object
                return [$result];
            }

            return array_values($result);
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('AI generation failed: ' . $exception->getMessage());
            return [];
        }
    }

    protected function getProducts(?string $productId, ?string $categoryId, bool $all)
    {
        $query = Product::query()->where('is_active', true);

        if ($productId) {
            return $query->where('id', $productId)->get();
        }

        if ($categoryId) {
            $categoryIds = Category::getCategoryNodeIdList(null, (int) $categoryId);
            if (empty($categoryIds)) {
                return collect();
            }
            return $query->whereHas('categories', fn($q) => 
                $q->whereIn('ak_product_categories.id', $categoryIds)
            )->get();
        }

        return $all ? $query->get() : collect();
    }

    protected function getBotUsers(string $countryCode, string $locale)
    {
        return Profile::query()
            ->where('role', 'bot')
            ->where(fn($q) => $q->where('country_code', $countryCode)->orWhere('locale', $locale))
            ->get();
    }

    protected function hasExistingBotReviews(Product $product): bool
    {
        return Review::query()
            ->where('reviewable_type', Product::class)
            ->where('reviewable_id', $product->id)
            ->where(fn($q) => $q
                ->whereJsonContains('extras->generated_by_bot', true)
                ->orWhereJsonContains('extras->skip_reward', true)
            )
            ->exists();
    }

    protected function getExistingReviewerIds(Product $product): array
    {
        return Review::query()
            ->where('reviewable_type', Product::class)
            ->where('reviewable_id', $product->id)
            ->whereNotNull('owner_id')
            ->pluck('owner_id')
            ->toArray();
    }

    protected function generateRating(): int
    {
        return rand(1, 100) <= 95 ? 5 : 4;
    }

    protected function createReview(Product $product, Profile $bot, array $reviewData, int $rating, bool $hasExtras): ?Review
    {
        $extras = [
            'verified_purchase' => '1',
            'skip_reward' => true,
            'generated_by_bot' => true,
            'bot_profile_id' => $bot->id,
        ];

        if ($hasExtras) {
            if (!empty($reviewData['advantages'])) {
                $advantages = $reviewData['advantages'];
                $extras['advantages'] = is_array($advantages) ? implode("\n", $advantages) : $advantages;
            }
            if (!empty($reviewData['flaws'])) {
                $flaws = $reviewData['flaws'];
                $extras['flaws'] = is_array($flaws) ? implode("\n", $flaws) : $flaws;
            }
        }

        $extras['owner'] = [
            'id' => $bot->id,
            'name' => $bot->fullname,
            'first_name' => $bot->first_name,
            'last_name' => $bot->last_name,
            'photo' => $bot->avatarUrl(),
        ];

        try {
            $review = new Review();
            $review->text = $reviewData['text'] ?? '';
            $review->rating = $rating;
            $review->owner_id = $bot->user_id ?? $bot->id;
            $review->reviewable_type = Product::class;
            $review->reviewable_id = $product->id;
            $review->is_moderated = false;
            $review->lang = $this->locale;
            $review->country = $this->countryCode;
            $review->extras = $extras;
            $review->save();

            return $review;
        } catch (\Throwable $exception) {
            $this->newLine();
            $this->error('Failed to create review: ' . $exception->getMessage());
            return null;
        }
    }
}
