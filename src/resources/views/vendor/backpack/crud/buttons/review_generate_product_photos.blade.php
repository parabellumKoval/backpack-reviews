@php
    $photoConfig = (array) config('backpack.reviews.generated_product_photos', []);
    $imageDriverOptions = (array) ($photoConfig['image_driver_options'] ?? ['gemini' => 'Gemini']);
    $imageModelOptions = (array) ($photoConfig['image_model_options'] ?? []);
    $promptDriverOptions = (array) ($photoConfig['prompt_driver_options'] ?? ['openai' => 'OpenAI']);
    $promptModelOptions = (array) ($photoConfig['prompt_model_options'] ?? []);
    $ensureOption = static function (array $options, mixed $value): array {
        if (! is_string($value) || trim($value) === '') {
            return $options;
        }

        if (! array_key_exists($value, $options)) {
            $options[$value] = $value;
        }

        return $options;
    };
    $imageDriverOptions = $ensureOption($imageDriverOptions, $photoConfig['image_driver'] ?? null);
    $imageModelOptions = $ensureOption($imageModelOptions, $photoConfig['image_model'] ?? null);
    $promptDriverOptions = $ensureOption($promptDriverOptions, $photoConfig['prompt_driver'] ?? null);
    $promptModelOptions = $ensureOption($promptModelOptions, $photoConfig['prompt_model'] ?? null);
    $defaultImageDriver = (string) ($photoConfig['image_driver'] ?? array_key_first($imageDriverOptions));
    $defaultImageModel = (string) ($photoConfig['image_model'] ?? array_key_first($imageModelOptions));
    $defaultPromptDriver = (string) ($photoConfig['prompt_driver'] ?? array_key_first($promptDriverOptions));
    $defaultPromptModel = (string) ($photoConfig['prompt_model'] ?? array_key_first($promptModelOptions));
@endphp

<button type="button" class="btn btn-primary" id="open-product-photo-generation-modal">
    <i class="la la-camera"></i> Генерировать пользовательские фото товаров
</button>

<div class="modal fade" id="productPhotoGenerationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Пакетная генерация пользовательских фото товаров</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="product-photo-generation-form">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="photo_generation_mode">Выборка товаров</label>
                            <select id="photo_generation_mode" class="form-control">
                                <option value="all">Все товары</option>
                                <option value="category">Товары категории</option>
                                <option value="brand">Товары бренда</option>
                                <option value="products">Конкретные товары</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4 mode-field d-none" data-mode="category">
                            <label for="photo_generation_category">Категория</label>
                            <select id="photo_generation_category" class="form-control"></select>
                        </div>
                        <div class="form-group col-md-4 mode-field d-none" data-mode="brand">
                            <label for="photo_generation_brand">Бренд</label>
                            <select id="photo_generation_brand" class="form-control"></select>
                        </div>
                        <div class="form-group col-md-8 mode-field d-none" data-mode="products">
                            <label for="photo_generation_products">Товары</label>
                            <select id="photo_generation_products" class="form-control" multiple></select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="photo_generation_products_limit">Лимит товаров</label>
                            <input type="number" min="1" max="5000" class="form-control" id="photo_generation_products_limit" value="30">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="photo_generation_per_product">Фото на товар</label>
                            <input type="number" min="1" max="50" class="form-control" id="photo_generation_per_product" value="10">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="photo_generation_total_limit">Лимит всех фото</label>
                            <input type="number" min="1" max="100000" class="form-control" id="photo_generation_total_limit" placeholder="Без лимита">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="photo_generation_image_driver">AI драйвер изображений</label>
                            <select id="photo_generation_image_driver" class="form-control">
                                @foreach($imageDriverOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($defaultImageDriver === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="photo_generation_image_model">AI модель изображений</label>
                            <select id="photo_generation_image_model" class="form-control">
                                @foreach($imageModelOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($defaultImageModel === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="photo_generation_prompt_driver">AI драйвер промптов</label>
                            <select id="photo_generation_prompt_driver" class="form-control">
                                @foreach($promptDriverOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($defaultPromptDriver === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="photo_generation_prompt_model">AI модель промптов</label>
                            <select id="photo_generation_prompt_model" class="form-control">
                                @foreach($promptModelOptions as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($defaultPromptModel === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Обрезка ватермарка (%)</label>
                            <div class="d-flex" style="gap:8px;">
                                <input type="number" min="0" max="20" class="form-control" id="photo_generation_crop_right" value="3" title="Справа">
                                <input type="number" min="0" max="20" class="form-control" id="photo_generation_crop_bottom" value="3" title="Снизу">
                            </div>
                            <small class="form-text text-muted">Справа / снизу</small>
                        </div>
                    </div>

                    <div class="form-row align-items-center">
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="photo_generation_skip_existing">
                                <label class="form-check-label" for="photo_generation_skip_existing">Учитывать существующие фото</label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="photo_generation_ai_prompts" checked>
                                <label class="form-check-label" for="photo_generation_ai_prompts">AI вариативные промпты</label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="photo_generation_validate_reference">
                                <label class="form-check-label" for="photo_generation_validate_reference">Проверять референс</label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="photo_generation_dry_run">
                                <label class="form-check-label" for="photo_generation_dry_run">Dry run</label>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="alert alert-danger d-none mt-3" id="product-photo-generation-error"></div>

                <div class="generation-panel mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Последние запуски</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="product-photo-generation-refresh">Обновить</button>
                    </div>
                    <div id="product-photo-generation-runs" class="generation-runs-empty text-muted">Запусков пока нет.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="submit-product-photo-generation">Запустить генерацию</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    #productPhotoGenerationModal .modal-dialog { max-width: 1120px; }
    .generation-run-card {
        border: 1px solid #d9e2ef;
        border-radius: 8px;
        padding: 12px 14px;
        background: #fff;
        margin-bottom: 10px;
    }
    .generation-run-card:last-child { margin-bottom: 0; }
    .generation-run-head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 8px;
    }
    .generation-run-status {
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .generation-run-status.is-queued { color: #9a6700; }
    .generation-run-status.is-running { color: #0c63e7; }
    .generation-run-status.is-completed { color: #137333; }
    .generation-run-status.is-failed { color: #b42318; }
    .generation-run-progress {
        height: 8px;
        background: #edf2f7;
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    .generation-run-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0d6efd, #6ea8fe);
        border-radius: 999px;
    }
</style>
@endpush

@push('after_styles')
<link href="{{ asset('packages/select2/dist/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('packages/select2-bootstrap-theme/dist/select2-bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('after_scripts')
<script src="{{ asset('packages/select2/dist/js/select2.full.min.js') }}"></script>
@php
    $select2LocalePath = 'packages/select2/dist/js/i18n/' . str_replace('_', '-', app()->getLocale()) . '.js';
@endphp
@if(is_file(public_path($select2LocalePath)))
    <script src="{{ asset($select2LocalePath) }}"></script>
@endif
@endpush

@push('after_scripts')
<script>
(function() {
    const modal = $('#productPhotoGenerationModal');
    const runsContainer = $('#product-photo-generation-runs');
    const errorBox = $('#product-photo-generation-error');
    const routes = {
        index: @json(route('bp.reviews.photo_generations.index')),
        store: @json(route('bp.reviews.photo_generations.store')),
        showTemplate: @json(route('bp.reviews.photo_generations.show', ['run' => '__RUN__'])),
        fetchCategory: @json(route('backpack.helpers.fetch', ['key' => 'category'])),
        fetchBrand: @json(route('backpack.helpers.fetch', ['key' => 'brand'])),
        fetchProduct: @json(route('backpack.helpers.fetch', ['key' => 'product'])),
    };
    const runType = 'product_review_photos';

    let pollTimer = null;

    function notify(type, text) {
        if (window.Noty) {
            new Noty({type, text}).show();
            return;
        }

        if (type === 'error') {
            window.alert(text);
        }
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function statusLabel(status) {
        switch (status) {
            case 'queued': return 'В очереди';
            case 'running': return 'Выполняется';
            case 'completed': return 'Завершено';
            case 'failed': return 'Ошибка';
            default: return status;
        }
    }

    function renderRun(run) {
        const percent = run.progress ? run.progress.percent : 0;
        const current = run.progress ? run.progress.current : 0;
        const total = run.progress ? run.progress.total : 0;
        const errorHtml = run.error_message ? `<div class="text-danger small mt-1">${escapeHtml(run.error_message)}</div>` : '';

        return `
            <div class="generation-run-card" data-run-id="${run.id}">
                <div class="generation-run-head">
                    <div>
                        <strong>#${run.id}</strong>
                        <div class="text-muted small">${escapeHtml(run.summary || 'Генерация фото товаров')}</div>
                    </div>
                    <div class="generation-run-status is-${run.status}">${escapeHtml(statusLabel(run.status))}</div>
                </div>
                <div class="generation-run-progress">
                    <div class="generation-run-progress-bar" style="width:${percent}%"></div>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span>${current}/${total || '?'}</span>
                    <span>${percent}%</span>
                </div>
                ${errorHtml}
            </div>
        `;
    }

    function renderRuns(runs) {
        if (!runs.length) {
            runsContainer.html('<div class="generation-runs-empty text-muted">Запусков пока нет.</div>');
            return;
        }

        runsContainer.html(runs.map(renderRun).join(''));
    }

    function clearPoll() {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function fetchRuns() {
        $.get(routes.index, {limit: 10, type: runType})
            .done(function(response) {
                const runs = response.data || [];
                renderRuns(runs);

                const active = runs.find(run => run.status === 'queued' || run.status === 'running');
                if (active) {
                    schedulePoll(active.id);
                }
            });
    }

    function schedulePoll(runId) {
        clearPoll();
        pollTimer = window.setTimeout(function() {
            $.get(routes.showTemplate.replace('__RUN__', String(runId)), {type: runType})
                .done(function(response) {
                    const currentRun = response.data;
                    fetchRuns();

                    if (currentRun && (currentRun.status === 'queued' || currentRun.status === 'running')) {
                        schedulePoll(runId);
                    }
                })
                .fail(function() {
                    schedulePoll(runId);
                });
        }, 4000);
    }

    function resetError() {
        errorBox.addClass('d-none').text('');
    }

    function showError(message) {
        errorBox.removeClass('d-none').text(message);
    }

    function initAjaxSelect(selector, url, multiple) {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }

        selector.select2({
            width: '100%',
            multiple: multiple,
            dropdownParent: modal,
            ajax: {
                url: url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1,
                    };
                },
                processResults: function(data) {
                    const rows = Array.isArray(data.data) ? data.data : (Array.isArray(data) ? data : []);
                    return {
                        results: rows.map(function(item) {
                            return {
                                id: item.id,
                                text: item.uniqString || item.uniqHtml || item.name || ('#' + item.id),
                            };
                        }),
                        pagination: { more: Boolean(data.next_page_url) }
                    };
                }
            }
        });
    }

    function updateModeFields() {
        const mode = $('#photo_generation_mode').val();
        $('.mode-field').addClass('d-none');
        $(`.mode-field[data-mode="${mode}"]`).removeClass('d-none');
    }

    function collectPayload() {
        return {
            selection_mode: $('#photo_generation_mode').val(),
            category_id: $('#photo_generation_category').val() || null,
            brand_id: $('#photo_generation_brand').val() || null,
            product_ids: $('#photo_generation_products').val() || [],
            products_limit: $('#photo_generation_products_limit').val() || null,
            photos_per_product: $('#photo_generation_per_product').val() || 10,
            photos_limit_total: $('#photo_generation_total_limit').val() || null,
            skip_existing: $('#photo_generation_skip_existing').is(':checked') ? 1 : 0,
            validate_reference: $('#photo_generation_validate_reference').is(':checked') ? 1 : 0,
            ai_prompt_variations: $('#photo_generation_ai_prompts').is(':checked') ? 1 : 0,
            image_driver: $('#photo_generation_image_driver').val() || null,
            image_model: $('#photo_generation_image_model').val() || null,
            prompt_driver: $('#photo_generation_prompt_driver').val() || null,
            prompt_model: $('#photo_generation_prompt_model').val() || null,
            watermark_crop_right_percent: $('#photo_generation_crop_right').val() || null,
            watermark_crop_bottom_percent: $('#photo_generation_crop_bottom').val() || null,
            dry_run: $('#photo_generation_dry_run').is(':checked') ? 1 : 0,
        };
    }

    $('#photo_generation_mode').on('change', updateModeFields);

    if (typeof $.fn.select2 === 'function') {
        initAjaxSelect($('#photo_generation_category'), routes.fetchCategory, false);
        initAjaxSelect($('#photo_generation_brand'), routes.fetchBrand, false);
        initAjaxSelect($('#photo_generation_products'), routes.fetchProduct, true);
    }

    $('#open-product-photo-generation-modal').on('click', function() {
        modal.appendTo('body').modal('show');
        resetError();
        updateModeFields();
        fetchRuns();
    });

    $('#product-photo-generation-refresh').on('click', fetchRuns);

    $('#submit-product-photo-generation').on('click', function() {
        resetError();

        $.ajax({
            url: routes.store,
            type: 'POST',
            data: {
                ...collectPayload(),
                _token: @json(csrf_token()),
            }
        }).done(function(response) {
            const run = response.data;
            notify('success', `Запуск #${run.id} поставлен в очередь.`);
            fetchRuns();
            schedulePoll(run.id);
        }).fail(function(xhr) {
            const validationErrors = xhr.responseJSON?.errors || null;
            let message = xhr.responseJSON?.message || 'Не удалось запустить генерацию фото.';

            if (validationErrors) {
                message = Object.values(validationErrors).flat().join(' ');
            }

            showError(message);
            notify('error', message);
        });
    });

    modal.on('hidden.bs.modal', clearPoll);
    updateModeFields();
})();
</script>
@endpush
