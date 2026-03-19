<?php

namespace Backpack\Reviews\app\Services;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ParabellumKoval\AiContentGenerator\Services\ContentGenerator;

class GeneratedPhotoPromptFactory
{
    protected const PROMPT_SETTINGS_PREFIX = 'rw.generated_product_photos.prompt';

    /** @var array<string, string> */
    protected array $selectionMemory = [];

    public function __construct(private readonly ContentGenerator $generator)
    {
    }

    public function build(Product $product, array $options = []): array
    {
        $brandName = trim((string) ($product->brand->name ?? ''));
        $productName = trim((string) ($product->name ?? ('Product #' . $product->id)));
        $productScope = (string) (int) ($product->id ?? 0);

        $orientation = $this->pickVariant('orientation', $productScope);
        $distance = $this->pickVariant('distance', $productScope);
        $camera = $this->pickVariant('camera', $productScope);
        $lighting = $this->pickVariant('lighting', $productScope);
        $scene = $this->pickVariant('scene', $productScope);
        $defects = $this->pickVariant('defects', $productScope);
        $packageState = $this->pickVariant('package_state', $productScope);

        $templates = $this->resolvePromptTemplates();

        $basePrompt = $this->composePrompt($templates, [
            'orientation' => $orientation,
            'distance' => $distance,
            'camera' => $camera,
            'lighting' => $lighting,
            'scene' => $scene,
            'defects' => $defects,
            'package_state' => $packageState,
            'product_name' => $productName,
            'brand_name' => $brandName,
        ]);

        $context = [
            'orientation' => $orientation,
            'distance' => $distance,
            'camera' => $camera,
            'lighting' => $lighting,
            'scene' => $scene,
            'defects' => $defects,
            'package_state' => $packageState,
            'product_name' => $productName,
            'brand_name' => $brandName,
            'templates' => $templates,
        ];

        $useAiVariations = (bool) ($options['ai_prompt_variations'] ?? true);
        if (!$useAiVariations) {
            return [
                'prompt' => $basePrompt,
                'context' => $context,
            ];
        }

        $rewrittenPrompt = $this->rewriteWithAi(
            $basePrompt,
            $product,
            $options,
            (string) ($templates['reference_instruction'] ?? '')
        );

        return [
            'prompt' => $rewrittenPrompt ?: $basePrompt,
            'context' => $context,
        ];
    }

    protected function resolvePromptTemplates(): array
    {
        $templateDefaults = (array) config('backpack.reviews.generated_product_photos.prompt.templates', []);

        $templates = [
            'reference_instruction' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.reference_instruction',
                Arr::get($templateDefaults, 'reference_instruction', '')
            ),
            'main_line' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.main_line',
                Arr::get($templateDefaults, 'main_line', '')
            ),
            'scene_line' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.scene_line',
                Arr::get($templateDefaults, 'scene_line', '')
            ),
            'camera_line' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.camera_line',
                Arr::get($templateDefaults, 'camera_line', '')
            ),
            'lighting_line' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.lighting_line',
                Arr::get($templateDefaults, 'lighting_line', '')
            ),
            'package_state_line' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.package_state_line',
                Arr::get($templateDefaults, 'package_state_line', '')
            ),
            'defects_line' => (string) $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.defects_line',
                Arr::get($templateDefaults, 'defects_line', '')
            ),
            'closing_lines' => $this->setting(
                self::PROMPT_SETTINGS_PREFIX . '.templates.closing_lines',
                Arr::get($templateDefaults, 'closing_lines', [])
            ),
        ];

        $templates['closing_lines'] = $this->normalizeClosingLines(
            is_array($templates['closing_lines']) ? $templates['closing_lines'] : []
        );

        return $templates;
    }

    protected function composePrompt(array $templates, array $variables): string
    {
        $lines = [];

        foreach ([
            'reference_instruction',
            'main_line',
            'scene_line',
            'camera_line',
            'lighting_line',
            'package_state_line',
            'defects_line',
        ] as $key) {
            $rendered = $this->renderTemplate((string) ($templates[$key] ?? ''), $variables);
            if ($rendered !== '') {
                $lines[] = $rendered;
            }
        }

        foreach ((array) ($templates['closing_lines'] ?? []) as $line) {
            $text = trim((string) $line);
            if ($text !== '') {
                $lines[] = $this->renderTemplate($text, $variables);
            }
        }

        return implode("\n", $lines);
    }

    protected function renderTemplate(string $template, array $variables): string
    {
        $template = trim($template);
        if ($template === '') {
            return '';
        }

        $replace = [];
        foreach ($variables as $key => $value) {
            $replace[':' . $key] = (string) $value;
        }

        return trim(strtr($template, $replace));
    }

    protected function normalizeClosingLines(array $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            if (is_string($row)) {
                $line = trim($row);
                if ($line !== '') {
                    $lines[] = $line;
                }
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $line = trim((string) ($row['line'] ?? $row['text'] ?? $row['value'] ?? ''));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return array_values($lines);
    }

    protected function pickVariant(string $variantKey, string $productScope): string
    {
        $variants = $this->resolveWeightedVariants($variantKey);
        if ($variants === []) {
            return '';
        }

        $preventImmediateRepeat = (bool) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.prevent_immediate_repeat',
            (bool) config('backpack.reviews.generated_product_photos.prompt.prevent_immediate_repeat', true)
        );
        $repeatPenaltyFactor = (float) $this->setting(
            self::PROMPT_SETTINGS_PREFIX . '.repeat_penalty_factor',
            (float) config('backpack.reviews.generated_product_photos.prompt.repeat_penalty_factor', 0.35)
        );
        $repeatPenaltyFactor = max(0.0, min(1.0, $repeatPenaltyFactor));

        $memoryKey = "{$productScope}:{$variantKey}";
        $lastSelected = $this->selectionMemory[$memoryKey] ?? null;
        $effectiveVariants = $variants;

        if (
            $preventImmediateRepeat
            && is_string($lastSelected)
            && $lastSelected !== ''
            && count($variants) > 1
            && $repeatPenaltyFactor < 1.0
        ) {
            foreach ($effectiveVariants as &$variant) {
                if ($variant['value'] === $lastSelected) {
                    $variant['weight'] = max(0.0, $variant['weight'] * $repeatPenaltyFactor);
                }
            }
            unset($variant);
        }

        $selected = $this->weightedRandomPick($effectiveVariants);
        $this->selectionMemory[$memoryKey] = $selected;

        return $selected;
    }

    protected function resolveWeightedVariants(string $variantKey): array
    {
        $defaults = (array) config("backpack.reviews.generated_product_photos.prompt.variants.{$variantKey}");

        $configured = $this->setting(self::PROMPT_SETTINGS_PREFIX . ".variants.{$variantKey}", $defaults);
        $variants = $this->normalizeWeightedVariants(is_array($configured) ? $configured : []);

        if ($variants === []) {
            $variants = $this->normalizeWeightedVariants($defaults);
        }

        if ($variants === []) {
            return [];
        }

        $totalWeight = array_sum(array_map(static fn (array $item) => $item['weight'], $variants));
        if ($totalWeight <= 0) {
            foreach ($variants as &$variant) {
                $variant['weight'] = 1.0;
            }
            unset($variant);
        }

        return $variants;
    }

    protected function normalizeWeightedVariants(array $rows): array
    {
        $normalizedByValue = [];

        foreach ($rows as $row) {
            $value = '';
            $weight = 1.0;

            if (is_string($row)) {
                $value = trim($row);
            } elseif (is_array($row)) {
                $value = trim((string) ($row['text'] ?? $row['value'] ?? $row['line'] ?? ''));
                if (array_key_exists('weight', $row)) {
                    $weight = (float) $row['weight'];
                }
            }

            if ($value === '') {
                continue;
            }

            $weight = max(0.0, $weight);
            if (!array_key_exists($value, $normalizedByValue)) {
                $normalizedByValue[$value] = 0.0;
            }
            $normalizedByValue[$value] += $weight;
        }

        return collect($normalizedByValue)
            ->map(fn (float $weight, string $value) => ['value' => $value, 'weight' => $weight])
            ->values()
            ->all();
    }

    protected function weightedRandomPick(array $variants): string
    {
        if ($variants === []) {
            return '';
        }

        $totalWeight = array_sum(array_map(static fn (array $item) => $item['weight'], $variants));
        if ($totalWeight <= 0) {
            $pick = Arr::random($variants);
            return (string) ($pick['value'] ?? '');
        }

        $threshold = (random_int(1, 1_000_000) / 1_000_000) * $totalWeight;
        $current = 0.0;
        $fallback = (string) ($variants[array_key_last($variants)]['value'] ?? '');

        foreach ($variants as $variant) {
            $weight = max(0.0, (float) ($variant['weight'] ?? 0.0));
            if ($weight <= 0) {
                continue;
            }

            $current += $weight;
            if ($threshold <= $current) {
                return (string) ($variant['value'] ?? $fallback);
            }
        }

        return $fallback;
    }

    protected function setting(string $key, mixed $default): mixed
    {
        try {
            return \Settings::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    protected function rewriteWithAi(string $basePrompt, Product $product, array $options, ?string $requiredInstruction = null): ?string
    {
        $instructionLine = trim((string) $requiredInstruction);
        if ($instructionLine === '') {
            $instructionLine = trim((string) config('backpack.reviews.generated_product_photos.prompt.templates.reference_instruction', ''));
        }

        $payload = [
            'prompt' => implode("\n", [
                'Rewrite the following image prompt for product UGC generation.',
                'Keep all constraints and realism defects.',
                sprintf('Do not remove instruction "%s".', $instructionLine),
                'Output only the final prompt text, no markdown, no comments.',
                '',
                'Product: ' . ($product->name ?? ('Product #' . $product->id)),
                'Base prompt:',
                $basePrompt,
            ]),
            'response_format' => 'text',
            'output_type' => 'single',
            'temperature' => 1.0,
            'max_tokens' => 500,
        ];

        if (!empty($options['prompt_driver'])) {
            $payload['driver'] = (string) $options['prompt_driver'];
        }

        if (!empty($options['prompt_model'])) {
            $payload['model'] = (string) $options['prompt_model'];
        }

        try {
            $response = $this->generator->generate($payload);
            $text = trim((string) $response->result);

            if ($text === '') {
                return null;
            }

            return Str::limit($text, 3000, '');
        } catch (\Throwable) {
            return null;
        }
    }
}
