<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Helpers\Traits\Admin\HasToggleColumns;
use Backpack\Reviews\app\Http\Requests\GoogleReviewRequest;
use Backpack\Reviews\app\Models\Admin\GoogleReview;
use Backpack\Reviews\app\Models\GoogleReviewLocation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ParabellumKoval\BackpackImages\Exceptions\ImageUploadException;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;
use Prologue\Alerts\Facades\Alert;

class GoogleReviewCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    use HasToggleColumns;

    public function setup()
    {
        $this->crud->setModel(GoogleReview::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/google-review');
        $this->crud->setEntityNameStrings('Google отзыв', 'Google отзывы');
    }

    protected function setupListOperation()
    {
        $this->crud->addButtonFromView('top', 'google_reviews_sync', 'google_reviews_sync', 'beginning');
        $this->crud->addButtonFromModelFunction('top', 'reviews_settings', 'getSettingsButtonHtml', 'end');

        $this->crud->addFilter([
            'name' => 'is_active',
            'type' => 'dropdown',
            'label' => 'Статус',
        ], [
            '1' => 'Только активные',
            '0' => 'Только неактивные',
        ], function ($value) {
            $this->crud->addClause('where', 'is_active', (int) $value);
        });

        $this->crud->orderBy('sort_order', 'desc');
        $this->crud->orderBy('review_created_at', 'desc');

        $this->addToggleColumn([
            'name' => 'is_active',
            'label' => 'Активен',
            'orderable' => true,
            'toggle' => [
                'values' => [
                    'checked' => 1,
                    'unchecked' => 0,
                ],
            ],
        ]);

        $this->crud->addColumn([
            'name' => 'sort_order',
            'label' => 'Приоритет',
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'review_created_at',
            'label' => 'Дата',
            'type' => 'datetime',
        ]);

        $this->crud->addColumn([
            'name' => 'rating',
            'label' => 'Оценка',
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'reviewer_name',
            'label' => 'Автор',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'reviewer_photo_url',
            'label' => 'Аватар',
            'type' => 'image',
            'height' => '36px',
            'width' => '36px',
        ]);

        $this->crud->addColumn([
            'name' => 'comment',
            'label' => 'Отзыв',
            'type' => 'text',
            'limit' => 120,
        ]);

        $this->crud->addColumn([
            'name' => 'location_name',
            'label' => 'Локация',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'synced_at',
            'label' => 'Синхронизация',
            'type' => 'datetime',
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(GoogleReviewRequest::class);
        $this->setupFormFields();
    }

    protected function setupUpdateOperation()
    {
        $this->crud->setValidation(GoogleReviewRequest::class);
        $this->setupFormFields();
    }

    protected function setupShowOperation()
    {
        CRUD::setFromDb();
    }

    public function store()
    {
        $this->normalizeFormData(true);

        return $this->traitStore();
    }

    public function update()
    {
        $this->normalizeFormData(false);

        return $this->traitUpdate();
    }

    public function syncNow()
    {
        try {
            Artisan::call('reviews:google:sync');
            Alert::success('Синхронизация Google отзывов запущена.')->flash();
        } catch (\Throwable $e) {
            Alert::error('Ошибка синхронизации Google отзывов: ' . $e->getMessage())->flash();
        }

        return back();
    }

    protected function setupFormFields(): void
    {
        $hasGoogleLocations = GoogleReviewLocation::query()->exists();

        CRUD::addField([
            'name' => 'review_name',
            'type' => 'hidden',
        ]);

        CRUD::addField([
            'name' => 'section_status',
            'type' => 'custom_html',
            'value' => '<h5 class="mb-3">Публикация и рейтинг</h5>',
        ]);

        CRUD::addField([
            'name' => 'is_active',
            'label' => 'Активный отзыв (показывать на фронте)',
            'type' => 'boolean',
            'default' => 1,
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'reviewer_is_anonymous',
            'label' => 'Анонимный автор',
            'type' => 'boolean',
            'default' => 0,
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'rating',
            'label' => 'Оценка',
            'type' => 'number',
            'attributes' => [
                'min' => 1,
                'max' => 5,
                'step' => 1,
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'sort_order',
            'label' => 'Приоритет вывода',
            'type' => 'number',
            'hint' => 'Чем больше число, тем выше отзыв в блоке на фронте.',
            'default' => 0,
            'attributes' => [
                'min' => 0,
                'step' => 1,
            ],
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'section_location',
            'type' => 'custom_html',
            'value' => '<hr><h5 class="mb-3">Локация и идентификаторы</h5>',
        ]);

        if ($hasGoogleLocations) {
            CRUD::addField([
                'name' => 'location_id',
                'label' => 'Локация из подключенного Google аккаунта (опционально)',
                'type' => 'relationship',
                'entity' => 'location',
                'attribute' => 'title',
                'model' => GoogleReviewLocation::class,
                'allows_null' => true,
                'hint' => 'Заполняется после OAuth-подключения. Можно не выбирать и указать локацию вручную полем ниже.',
                'wrapper' => [
                    'class' => 'form-group col-md-6',
                ],
            ]);
        } else {
            CRUD::addField([
                'name' => 'location_notice',
                'type' => 'custom_html',
                'value' => '<div class="alert alert-info">Подключенные Google-локации пока не найдены. Можно создать отзыв вручную, заполнив поле "Локация (название)".</div>',
            ]);
        }

        CRUD::addField([
            'name' => 'location_name',
            'label' => 'Локация (название)',
            'type' => 'text',
            'hint' => 'Что увидит пользователь на фронте, например: "Praha, Vaclavske namesti".',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'review_id',
            'label' => 'ID отзыва в Google (необязательно)',
            'type' => 'text',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'section_author',
            'type' => 'custom_html',
            'value' => '<hr><h5 class="mb-3">Автор</h5>',
        ]);

        CRUD::addField([
            'name' => 'reviewer_name',
            'label' => 'Имя автора',
            'type' => 'text',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'reviewer_photo_url',
            'label' => 'Аватар автора',
            'type' => 'image',
            'hint' => 'Загрузка через backpack-images (можно загрузить файл или вставить URL).',
            'crop' => false,
            'aspect_ratio' => 1,
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'section_content',
            'type' => 'custom_html',
            'value' => '<hr><h5 class="mb-3">Содержимое</h5>',
        ]);

        CRUD::addField([
            'name' => 'comment',
            'label' => 'Текст отзыва',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'reply_comment',
            'label' => 'Ответ бизнеса',
            'type' => 'textarea',
        ]);

        CRUD::addField([
            'name' => 'section_dates',
            'type' => 'custom_html',
            'value' => '<hr><h5 class="mb-3">Даты</h5>',
        ]);

        CRUD::addField([
            'name' => 'review_created_at',
            'label' => 'Дата создания отзыва',
            'type' => 'datetime',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'review_updated_at',
            'label' => 'Дата обновления отзыва',
            'type' => 'datetime',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'reply_updated_at',
            'label' => 'Дата обновления ответа',
            'type' => 'datetime',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);

        CRUD::addField([
            'name' => 'synced_at',
            'label' => 'Дата синхронизации',
            'type' => 'datetime',
            'wrapper' => [
                'class' => 'form-group col-md-6',
            ],
        ]);
    }

    protected function normalizeFormData(bool $fillCreateDefaults): void
    {
        $data = request()->all();
        $currentEntry = null;

        if (!$fillCreateDefaults) {
            $entryId = $this->crud->getCurrentEntryId()
                ?? request()->route('id')
                ?? request()->input('id');

            if ($entryId) {
                $currentEntry = GoogleReview::query()->find($entryId);
            }
        }

        if (empty($data['location_name']) && !empty($data['location_id'])) {
            $location = GoogleReviewLocation::query()->find($data['location_id']);
            if ($location) {
                $data['location_name'] = $location->title ?: $location->location_name;
            }
        }

        if ($fillCreateDefaults && empty($data['review_name'])) {
            $data['review_name'] = 'manual/' . Str::uuid()->toString();
        }

        if ($fillCreateDefaults && empty($data['review_created_at'])) {
            $data['review_created_at'] = now()->toDateTimeString();
        }

        if ($fillCreateDefaults && empty($data['synced_at'])) {
            $data['synced_at'] = now()->toDateTimeString();
        }

        if ($fillCreateDefaults && !array_key_exists('is_active', $data)) {
            $data['is_active'] = 1;
        }

        if ($fillCreateDefaults && !array_key_exists('sort_order', $data)) {
            $data['sort_order'] = 0;
        }

        if (array_key_exists('reviewer_photo_url', $data)) {
            $photoSource = trim((string) $data['reviewer_photo_url']);

            if ($photoSource === '') {
                $data['reviewer_photo_url'] = null;
                $data['reviewer_photo_path'] = null;
            } else {
                $hasChanged = !$currentEntry || $photoSource !== (string) $currentEntry->reviewer_photo_url;

                if ($hasChanged) {
                    $uploadedAvatar = $this->uploadAvatarWithBackpackImages($photoSource);

                    if ($uploadedAvatar) {
                        $data['reviewer_photo_url'] = $uploadedAvatar['url'];
                        $data['reviewer_photo_path'] = $uploadedAvatar['path'];
                    } elseif ($this->isLocalAvatarSource($photoSource)) {
                        $data['reviewer_photo_url'] = $this->normalizeLocalAvatarUrl($photoSource);
                        $data['reviewer_photo_path'] = $this->extractPublicDiskPath($photoSource);
                    } else {
                        $data['reviewer_photo_url'] = $photoSource;
                        $data['reviewer_photo_path'] = null;
                    }
                }
            }
        }

        request()->merge($data);
    }

    protected function isLocalAvatarSource(string $source): bool
    {
        if (Str::startsWith($source, 'data:image')) {
            return false;
        }

        if (!filter_var($source, FILTER_VALIDATE_URL)) {
            return true;
        }

        $sourceHost = (string) parse_url($source, PHP_URL_HOST);
        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $sourcePath = (string) parse_url($source, PHP_URL_PATH);

        return $sourceHost !== '' && $appHost !== ''
            && strcasecmp($sourceHost, $appHost) === 0
            && Str::startsWith($sourcePath, '/storage/');
    }

    protected function normalizeLocalAvatarUrl(string $source): string
    {
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return $source;
        }

        return '/' . ltrim(str_replace('\\', '/', $source), '/');
    }

    protected function extractPublicDiskPath(string $source): ?string
    {
        $path = filter_var($source, FILTER_VALIDATE_URL)
            ? (string) parse_url($source, PHP_URL_PATH)
            : $source;

        $normalizedPath = '/' . ltrim(str_replace('\\', '/', $path), '/');
        if (!Str::startsWith($normalizedPath, '/storage/')) {
            return null;
        }

        return ltrim(Str::after($normalizedPath, '/storage/'), '/');
    }

    protected function uploadAvatarWithBackpackImages(string $photoSource): ?array
    {
        $photoSource = trim($photoSource);
        if ($photoSource === '') {
            return null;
        }

        if ($this->isLocalAvatarSource($photoSource)) {
            return null;
        }

        $options = new ImageUploadOptions(folder: 'reviews/google-avatars');
        $uploader = app(ImageUploader::class);

        try {
            if (Str::startsWith($photoSource, 'data:image')) {
                $stored = $uploader->uploadFromBase64($photoSource, $options);
            } elseif (filter_var($photoSource, FILTER_VALIDATE_URL)) {
                $stored = $uploader->upload($photoSource, $options);
            } else {
                return null;
            }

            return [
                'url' => $stored->url,
                'path' => $stored->path,
            ];
        } catch (ImageUploadException|\Throwable $exception) {
            Log::warning('google_reviews.avatar.upload_failed', [
                'source' => Str::limit($photoSource, 500),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
