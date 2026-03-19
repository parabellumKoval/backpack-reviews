<?php

namespace Backpack\Reviews\app\Settings;

use Backpack\Settings\Contracts\SettingsRegistrarInterface;
use Backpack\Settings\Services\Registry\Registry;
use Backpack\Settings\Services\Registry\Field;
use Illuminate\Support\Facades\Route;

class ReviewsSettingsRegistrar implements SettingsRegistrarInterface
{
    public function register(Registry $registry): void
    {
        $registry->group('reviews', function ($group) {
            $group->title('Настройки отзывов')->icon('la la-store');

            $group->page('Общие', function ($page) {
                $page->add(Field::make('rw.enabled', 'radio')
                    ->label('Включить отзывы?')
                    ->default('no_moderation')
                    ->cast('string')
                    ->options([
                        1 => "Включить",
                        0 => "Выключить"
                    ])
                    ->inline(true)
                    ->hint('Настройка будет применена глобально на всем сайте')
                );

                $page->add(Field::make("rw.allow_publish", 'checkbox')
                    ->label('Разрешить добавление новых отзывов?')
                    ->default(false)
                    ->cast('bool')
                    ->hint('Настройка будет применена глобально на всем сайте')
                );
            });

            $group->page('Google Business Profile', function ($page) {
                $page->add(Field::make('rw.google.enabled', 'checkbox')
                    ->label('Google отзывы: включить')
                    ->default(false)
                    ->cast('bool')
                );

                $page->add(Field::make('rw.google.client_id', 'text')
                    ->label('Google OAuth Client ID')
                    ->default('')
                    ->cast('string')
                );

                $page->add(Field::make('rw.google.client_secret', 'password')
                    ->label('Google OAuth Client Secret')
                    ->default('')
                    ->cast('string')
                );

                $page->add(Field::make('rw.google.redirect_uri', 'text')
                    ->label('OAuth Redirect URI')
                    ->default('')
                    ->cast('string')
                );

                $oauthUrl = Route::has('bp.reviews.google.oauth')
                    ? route('bp.reviews.google.oauth')
                    : url('admin/reviews/google/oauth');

                $page->add(Field::make('rw.google.connect', 'custom_html')
                    ->label('Подключение')
                    ->default('<a class="btn btn-primary" href="' . e($oauthUrl) . '">Подключить Google Business Profile</a>')
                );
            });

            $group->page('Генерация фото товаров', function ($page) {
                $page->add(Field::make('rw.generated_product_photos.prompt._info', 'custom_html')
                    ->label('Как это работает')
                    ->default('<div class="alert alert-info mb-0">Настраивайте текст промпта и варианты с весами. Вес работает как вероятность: при значениях 5/3/2 варианты будут выпадать примерно в пропорции 50%/30%/20%. Система дополнительно снижает повтор одного и того же варианта подряд, чтобы кадры не были однотипными. Для пользовательских фото лучше делать упор на слабую камеру, плохой свет, смаз, блики и шум, а грязь и пыль держать редкими и едва заметными.</div>')
                    ->tab('Справка')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.reference_instruction', 'textarea')
                    ->label('Строка-ограничение (обязательно сохраняется при AI-переписывании)')
                    ->default($this->promptTemplateDefault('reference_instruction'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.main_line', 'textarea')
                    ->label('Основная строка')
                    ->default($this->promptTemplateDefault('main_line'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->hint('Поддерживаются плейсхолдеры: :product_name, :brand_name, :orientation, :distance')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.scene_line', 'textarea')
                    ->label('Строка сцены')
                    ->default($this->promptTemplateDefault('scene_line'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->hint('Плейсхолдер: :scene')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.camera_line', 'textarea')
                    ->label('Строка камеры / типа съемки')
                    ->default($this->promptTemplateDefault('camera_line'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->hint('Плейсхолдер: :camera')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.lighting_line', 'textarea')
                    ->label('Строка освещения')
                    ->default($this->promptTemplateDefault('lighting_line'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->hint('Плейсхолдер: :lighting')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.package_state_line', 'textarea')
                    ->label('Строка состояния упаковки')
                    ->default($this->promptTemplateDefault('package_state_line'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->hint('Плейсхолдер: :package_state')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.defects_line', 'textarea')
                    ->label('Строка дефектов')
                    ->default($this->promptTemplateDefault('defects_line'))
                    ->cast('string')
                    ->attributes(['rows' => 2])
                    ->hint('Плейсхолдер: :defects')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.templates.closing_lines', 'repeatable_pure')
                    ->label('Финальные ограничения (каждая строка отдельно)')
                    ->fields([
                        [
                            'name' => 'line',
                            'type' => 'text',
                            'label' => 'Строка',
                        ],
                    ])
                    ->newItemLabel('Добавить ограничение')
                    ->default($this->promptClosingLinesDefault())
                    ->cast('array')
                    ->tab('Шаблон')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.orientation', 'repeatable_pure')
                    ->label('Ориентация кадра')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить ориентацию')
                    ->default($this->promptVariantDefault('orientation'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.distance', 'repeatable_pure')
                    ->label('Дистанция / кадрирование')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить вариант кадрирования')
                    ->default($this->promptVariantDefault('distance'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.camera', 'repeatable_pure')
                    ->label('Камера / тип съемки')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить вариант камеры')
                    ->default($this->promptVariantDefault('camera'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.lighting', 'repeatable_pure')
                    ->label('Освещение')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить вариант освещения')
                    ->default($this->promptVariantDefault('lighting'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.scene', 'repeatable_pure')
                    ->label('Сцена / интерьер')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить сцену')
                    ->default($this->promptVariantDefault('scene'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.defects', 'repeatable_pure')
                    ->label('Дефекты качества')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить дефект')
                    ->default($this->promptVariantDefault('defects'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.variants.package_state', 'repeatable_pure')
                    ->label('Состояние упаковки')
                    ->fields($this->weightedVariantSubfields())
                    ->newItemLabel('Добавить состояние')
                    ->default($this->promptVariantDefault('package_state'))
                    ->cast('array')
                    ->tab('Варианты')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.prevent_immediate_repeat', 'checkbox')
                    ->label('Снижать повтор одного и того же варианта подряд')
                    ->default((bool) config('backpack.reviews.generated_product_photos.prompt.prevent_immediate_repeat', true))
                    ->cast('bool')
                    ->tab('Распределение')
                );

                $page->add(Field::make('rw.generated_product_photos.prompt.repeat_penalty_factor', 'number')
                    ->label('Коэффициент вероятности повтора подряд')
                    ->default((float) config('backpack.reviews.generated_product_photos.prompt.repeat_penalty_factor', 0.35))
                    ->cast('float')
                    ->attrs(['min' => 0, 'max' => 1, 'step' => 0.05])
                    ->hint('0 = почти исключить мгновенный повтор, 1 = без штрафа повтора.')
                    ->tab('Распределение')
                );
            });

            $reviewable_models = \Settings::get('backpack.reviews.reviewable_types_list');

            foreach($reviewable_models as $key => $params) {
                $group->page($params['name_plur'], function ($page) use ($params, $key) {
                    $page->add(Field::make("rw.{$key}.enabled", 'radio')
                        ->label('Включить отзывы?')
                        ->default('no_moderation')
                        ->cast('string')
                        ->options([
                            1 => "Включить",
                            0 => "Выключить"
                        ])
                        ->inline(true)
                        ->tab('Основное')
                    );

                    $page->add(Field::make("rw.{$key}.allow_publish", 'checkbox')
                        ->label('Разрешить добавление новых отзывов?')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Если включить то пользователи смогут добавлять новые отзывы')
                        ->tab('Основное')
                    );

                    $page->add(Field::make("rw.{$key}.publish_strategy", 'radio')
                        ->label('Политика публикации отзывов')
                        ->default('no_moderation')
                        ->cast('string')
                        ->options([
                            'no_moderation' => "Сразу (без модерации)",
                            'with_moderation' => "Только после модерации"
                        ])
                        ->inline(true)
                        ->tab('Основное')
                    );

                    $page->add(Field::make("rw.{$key}.reply", 'checkbox')
                        ->label('Ответы на отзывы')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Дать возможность пользователям отвечать/комментировать другие отзывы.')
                        ->tab('Настройки')
                    );

                    $page->add(Field::make("rw.{$key}.is_visible", 'checkbox')
                        ->label('Включить оценки')
                        ->default(false)
                        ->cast('bool')
                        ->hint('Если включить то пользователи смогут выставлять оценки (формировать рейтинг)')
                        ->tab('Настройки')
                    );
                });
            }
        });
    }

    protected function promptTemplateDefault(string $key): string
    {
        return (string) config(
            "backpack.reviews.generated_product_photos.prompt.templates.{$key}",
            ''
        );
    }

    protected function promptClosingLinesDefault(): array
    {
        $rows = config(
            'backpack.reviews.generated_product_photos.prompt.templates.closing_lines',
            []
        );

        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $line = trim($row);
                if ($line !== '') {
                    $normalized[] = ['line' => $line];
                }
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $line = trim((string) ($row['line'] ?? ''));
            if ($line !== '') {
                $normalized[] = ['line' => $line];
            }
        }

        return $normalized;
    }

    protected function weightedVariantSubfields(): array
    {
        return [
            [
                'name' => 'text',
                'type' => 'text',
                'label' => 'Вариант',
            ],
            [
                'name' => 'weight',
                'type' => 'number',
                'label' => 'Вес',
                'attributes' => ['min' => 0, 'step' => 1],
            ],
        ];
    }

    protected function promptVariantDefault(string $variant): array
    {
        $rows = config(
            "backpack.reviews.generated_product_photos.prompt.variants.{$variant}",
            []
        );

        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $value = trim($row);
                if ($value !== '') {
                    $normalized[] = ['text' => $value, 'weight' => 1];
                }
                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $text = trim((string) ($row['text'] ?? $row['value'] ?? ''));
            if ($text === '') {
                continue;
            }

            $weight = (int) ($row['weight'] ?? 1);
            if ($weight < 0) {
                $weight = 0;
            }

            $normalized[] = ['text' => $text, 'weight' => $weight];
        }

        return $normalized;
    }
}
