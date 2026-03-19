<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Reviews\app\Http\Requests\GeneratedProductPhotoRequest;
use Backpack\Reviews\app\Models\GeneratedProductPhoto;
use Backpack\Reviews\app\Services\GeneratedProductPhotoModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Backpack\Helpers\Traits\Admin\HasToggleColumns;
use ParabellumKoval\BackpackImages\Traits\HasImagesCrudComponents;

class GeneratedProductPhotoCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    use HasToggleColumns;
    use HasImagesCrudComponents;

    public function setup(): void
    {
        CRUD::setModel(GeneratedProductPhoto::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/generated-product-photo');
        CRUD::setEntityNameStrings('сгенерированное фото товара', 'сгенерированные фото товаров');
        CRUD::orderBy('id', 'desc');
    }

    protected function setupListOperation(): void
    {
        $this->crud->addButtonFromView('top', 'generate_product_photos', 'review_generate_product_photos', 'end');
        $this->crud->addButtonFromView('top', 'moderate_product_photos', 'review_moderate_product_photos', 'end');

        CRUD::addColumn([
            'name' => 'id',
            'label' => '#',
        ]);

        CRUD::addColumn([
            'name' => 'product_id',
            'label' => 'Товар',
            'type' => 'closure',
            'escaped' => false,
            'function' => function (GeneratedProductPhoto $entry) {
                $product = $entry->product;

                if (!$product) {
                    return '<span class="text-muted">Товар не найден</span>';
                }

                if (method_exists($product, 'getCrudCardHtml')) {
                    return $product->getCrudCardHtml(['compact' => true]);
                }

                return e(($product->name ?? 'Product #' . $product->id) . ' (#' . $product->id . ')');
            },
        ]);

        CRUD::addColumn([
            'name' => 'image',
            'label' => 'Фото',
            'type' => 'image_modal',
            'height' => '200px',
            'width' => '200px',
        ]);

        $this->addToggleColumn([
            'name' => 'is_reviewed',
            'label' => '<span title="Проверено / не проверено"><i class="la la-eye"></i></span>',
            'priority' => 4,
            'orderable' => false,
            'toggle' => [
                'attribute' => 'status',
                'values' => [
                    'checked' => GeneratedProductPhoto::STATUS_APPROVED,
                    'unchecked' => GeneratedProductPhoto::STATUS_PENDING_REVIEW,
                ],
            ],
        ]);

        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Статус',
        ]);

        CRUD::addColumn([
            'name' => 'driver',
            'label' => 'Драйвер',
        ]);

        CRUD::addColumn([
            'name' => 'model',
            'label' => 'Модель',
        ]);

        CRUD::addColumn([
            'name' => 'error_message',
            'label' => 'Ошибка',
            'type' => 'textarea',
        ]);

        CRUD::addColumn([
            'name' => 'created_at',
            'label' => 'Создано',
            'type' => 'datetime',
        ]);

        CRUD::addColumn([
            'name' => 'reviewed_at',
            'label' => 'Проверено',
            'type' => 'datetime',
        ]);

        CRUD::addFilter([
            'name' => 'status',
            'type' => 'dropdown',
            'label' => 'Статус',
        ], [
            GeneratedProductPhoto::STATUS_PENDING_REVIEW => 'Не проверено',
            GeneratedProductPhoto::STATUS_APPROVED => 'Одобрено',
            GeneratedProductPhoto::STATUS_REJECTED => 'Отклонено',
            GeneratedProductPhoto::STATUS_FAILED => 'Ошибка',
        ], function ($value) {
            CRUD::addClause('where', 'status', $value);
        });
    }

    public function toggleColumnRouter($id): JsonResponse
    {
        $this->crud->hasAccessOrFail('update');
        $this->ensureToggleColumnsRegistered();

        $columnName = request()->input('column');
        $submittedValue = request()->input('value');

        if (!$columnName || !array_key_exists($columnName, $this->toggleColumnsConfig)) {
            abort(404, 'Toggle column not found.');
        }

        $config = $this->toggleColumnsConfig[$columnName];
        $resolvedValue = $this->resolveToggleValue($config['values'], $submittedValue);

        /** @var GeneratedProductPhoto $entry */
        $entry = $this->crud->model->findOrFail($id);

        if ($config['attribute'] === 'status') {
            if ($resolvedValue === GeneratedProductPhoto::STATUS_APPROVED) {
                $entry->markApproved(backpack_user()?->id);
            } elseif ($resolvedValue === GeneratedProductPhoto::STATUS_PENDING_REVIEW) {
                $entry->forceFill([
                    'status' => GeneratedProductPhoto::STATUS_PENDING_REVIEW,
                    'reviewed_by_id' => null,
                    'reviewed_at' => null,
                    'approved_at' => null,
                ])->save();
            } else {
                $entry->forceFill([$config['attribute'] => $resolvedValue])->save();
            }
        } else {
            $entry->forceFill([$config['attribute'] => $resolvedValue])->save();
        }

        return response()->json([
            'success' => true,
            'column' => $columnName,
            'value' => $resolvedValue,
        ]);
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation(GeneratedProductPhotoRequest::class);

        CRUD::addField([
            'name' => 'product_id',
            'label' => 'Товар',
            'type' => 'relationship',
            'entity' => 'product',
            'model' => config('backpack.reviews.generated_product_photos.product_model', \App\Models\Product::class),
            'attribute' => 'uniqHtml',
            'data_source' => route('backpack.helpers.fetch', ['key' => 'product_base']),
            'ajax' => true,
            'minimum_input_length' => 0,
        ]);

        $this->addImagesField([
            'name' => 'image',
            'label' => 'Фото',
            'hint' => 'Можно добавить вручную или через массовую генерацию.',
        ]);

        CRUD::addField([
            'name' => 'status',
            'label' => 'Статус',
            'type' => 'select_from_array',
            'options' => [
                GeneratedProductPhoto::STATUS_PENDING_REVIEW => 'Не проверено',
                GeneratedProductPhoto::STATUS_APPROVED => 'Одобрено',
                GeneratedProductPhoto::STATUS_REJECTED => 'Отклонено',
                GeneratedProductPhoto::STATUS_FAILED => 'Ошибка',
            ],
            'default' => GeneratedProductPhoto::STATUS_PENDING_REVIEW,
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'prompt',
            'label' => 'Промпт',
            'type' => 'textarea',
            'attributes' => ['rows' => 6],
        ]);

        CRUD::addField([
            'name' => 'error_message',
            'label' => 'Ошибка',
            'type' => 'textarea',
            'attributes' => ['rows' => 3],
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }

    public function moderation(Request $request)
    {
        $batchSize = max(1, min(100, (int) $request->integer('batch', (int) config('backpack.reviews.generated_product_photos.moderation_batch', 20))));

        $batch = GeneratedProductPhoto::query()
            ->pendingReview()
            ->with('product')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $remaining = GeneratedProductPhoto::query()
            ->pendingReview()
            ->whereNotIn('id', $batch->pluck('id'))
            ->count();

        return view('reviews::generated_product_photo_moderation', [
            'batch' => $batch,
            'batchSize' => $batchSize,
            'remaining' => $remaining,
            'submitUrl' => route('bp.reviews.generated-photos.moderation.approve'),
        ]);
    }

    public function moderateBatch(Request $request, GeneratedProductPhotoModerationService $moderationService): JsonResponse
    {
        $payload = $request->validate([
            'displayed_ids' => ['required', 'array', 'min:1'],
            'displayed_ids.*' => ['integer', 'min:1'],
            'delete_ids' => ['nullable', 'array'],
            'delete_ids.*' => ['integer', 'min:1'],
        ]);

        $displayedIds = collect($payload['displayed_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $deleteIds = collect($payload['delete_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();

        $batch = GeneratedProductPhoto::query()
            ->pendingReview()
            ->whereIn('id', $displayedIds)
            ->get();

        $result = $moderationService->moderateBatch(
            $batch,
            $deleteIds,
            backpack_user()?->id
        );

        $remaining = GeneratedProductPhoto::query()->pendingReview()->count();

        return response()->json([
            'data' => [
                'approved' => (int) ($result['approved'] ?? 0),
                'deleted' => (int) ($result['deleted'] ?? 0),
                'remaining' => $remaining,
            ],
        ]);
    }
}
