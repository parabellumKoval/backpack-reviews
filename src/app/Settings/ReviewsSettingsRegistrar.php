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

                $page->add(Field::make('rw.google.enabled', 'checkbox')
                    ->label('Google отзывы: включить')
                    ->default(false)
                    ->cast('bool')
                    ->tab('Google Business Profile')
                );

                $page->add(Field::make('rw.google.client_id', 'text')
                    ->label('Google OAuth Client ID')
                    ->default('')
                    ->cast('string')
                    ->tab('Google Business Profile')
                );

                $page->add(Field::make('rw.google.client_secret', 'password')
                    ->label('Google OAuth Client Secret')
                    ->default('')
                    ->cast('string')
                    ->tab('Google Business Profile')
                );

                $page->add(Field::make('rw.google.redirect_uri', 'text')
                    ->label('OAuth Redirect URI')
                    ->default('')
                    ->cast('string')
                    ->tab('Google Business Profile')
                );

                if (!app()->runningInConsole()) {
                    $oauthUrl = Route::has('bp.reviews.google.oauth')
                        ? route('bp.reviews.google.oauth')
                        : url('admin/reviews/google/oauth');

                    $page->add(Field::make('rw.google.connect', 'custom_html')
                        ->label('Подключение')
                        ->tab('Google Business Profile')
                        ->value('<a class="btn btn-primary" href="' . e($oauthUrl) . '">Подключить Google Business Profile</a>')
                    );
                }
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
}
