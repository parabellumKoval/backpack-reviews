@php
    $localeOptions = collect((array) config('app.supported_locales', []))
        ->mapWithKeys(fn ($locale) => [strtolower((string) $locale) => strtoupper((string) $locale)])
        ->all();

    $normalizeCountryCode = static function (?string $code): ?string {
        $code = strtoupper(trim((string) $code));

        return match ($code) {
            'UK' => 'UA',
            default => $code !== '' ? $code : null,
        };
    };

    $countryOptions = collect((array) \Backpack\Store\app\Services\Store::countries())
        ->mapWithKeys(function ($item, $code) use ($normalizeCountryCode) {
            $normalized = $normalizeCountryCode($item['code'] ?? $code);

            return $normalized ? [$normalized => (($item['country'] ?? $normalized) . ' (' . $normalized . ')')] : [];
        })
        ->merge([
            'UA' => 'Ukraine (UA)',
            'CZ' => 'Czech Republic (CZ)',
            'DE' => 'Germany (DE)',
            'ES' => 'Spain (ES)',
        ])
        ->sortKeys()
        ->all();
@endphp

<button type="button" class="btn btn-primary" id="open-review-generation-modal">
    <i class="la la-magic"></i> Генерация отзывов
</button>

<div class="modal fade" id="reviewGenerationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Пакетная генерация отзывов</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="review-generation-form">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="review_generation_mode">Что выбрать</label>
                            <select id="review_generation_mode" class="form-control">
                                <option value="all">Все товары</option>
                                <option value="category">Товары категории</option>
                                <option value="brand">Товары бренда</option>
                                <option value="products">Конкретные товары</option>
                                <option value="no_reviews">Товары без отзывов</option>
                                <option value="low_reviews">Товары с малым числом отзывов</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4 mode-field d-none" data-mode="category">
                            <label for="review_generation_category">Категория</label>
                            <select id="review_generation_category" class="form-control"></select>
                        </div>
                        <div class="form-group col-md-4 mode-field d-none" data-mode="brand">
                            <label for="review_generation_brand">Бренд</label>
                            <select id="review_generation_brand" class="form-control"></select>
                        </div>
                        <div class="form-group col-md-4 mode-field d-none" data-mode="low_reviews">
                            <label for="review_generation_review_count_max">Макс. отзывов у товара</label>
                            <input type="number" min="1" max="9999" class="form-control" id="review_generation_review_count_max" value="3">
                        </div>
                        <div class="form-group col-md-8 mode-field d-none" data-mode="products">
                            <label for="review_generation_products">Товары</label>
                            <select id="review_generation_products" class="form-control" multiple></select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="review_generation_product_limit">Лимит товаров</label>
                            <input type="number" min="1" max="5000" class="form-control" id="review_generation_product_limit" value="5">
                            <small class="form-text text-muted">Работает для любого режима.</small>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="review_generation_min">Мин. отзывов на товар</label>
                            <input type="number" min="1" max="100" class="form-control" id="review_generation_min" value="5">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="review_generation_max">Макс. отзывов на товар</label>
                            <input type="number" min="1" max="100" class="form-control" id="review_generation_max" value="20">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="review_generation_schedule_start">Старт публикации</label>
                            <input type="text" class="form-control" id="review_generation_schedule_start" value="tomorrow">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="review_generation_locales">Языки отзывов</label>
                            <select id="review_generation_locales" class="form-control" multiple>
                                @foreach($localeOptions as $localeCode => $localeLabel)
                                    <option value="{{ $localeCode }}">{{ $localeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="review_generation_countries">Страны</label>
                            <select id="review_generation_countries" class="form-control" multiple>
                                @foreach($countryOptions as $countryCode => $countryLabel)
                                    <option value="{{ $countryCode }}">{{ $countryLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="review_generation_schedule_min_per_day">Мин. публикаций в день</label>
                            <input type="number" min="1" max="100" class="form-control" id="review_generation_schedule_min_per_day" value="1">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="review_generation_schedule_max_per_day">Макс. публикаций в день</label>
                            <input type="number" min="1" max="100" class="form-control" id="review_generation_schedule_max_per_day" value="1">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="review_generation_schedule_hour_from">Час от</label>
                            <input type="number" min="0" max="23" class="form-control" id="review_generation_schedule_hour_from" value="9">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="review_generation_schedule_hour_to">Час до</label>
                            <input type="number" min="0" max="23" class="form-control" id="review_generation_schedule_hour_to" value="21">
                        </div>
                    </div>

                    <div class="form-row align-items-center">
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="review_generation_skip_existing">
                                <label class="form-check-label" for="review_generation_skip_existing">Пропускать товары с bot-отзывами</label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="review_generation_publish_now">
                                <label class="form-check-label" for="review_generation_publish_now">Публиковать сразу</label>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="review_generation_dry_run">
                                <label class="form-check-label" for="review_generation_dry_run">Dry run</label>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="alert alert-danger d-none mt-3" id="review-generation-error"></div>

                <div class="generation-panel mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Последние запуски</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="review-generation-refresh">Обновить</button>
                    </div>
                    <div id="review-generation-runs" class="generation-runs-empty text-muted">Запусков пока нет.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="submit-review-generation">Запустить генерацию</button>
            </div>
        </div>
    </div>
</div>

@push('after_styles')
<style>
    #reviewGenerationModal .modal-dialog {
        max-width: 1120px;
    }

    .generation-run-card {
        border: 1px solid #d9e2ef;
        border-radius: 8px;
        padding: 12px 14px;
        background: #fff;
        margin-bottom: 10px;
    }

    .generation-run-card:last-child {
        margin-bottom: 0;
    }

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

    .generation-runs-empty {
        padding: 12px 0;
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
    const modal = $('#reviewGenerationModal');
    const runsContainer = $('#review-generation-runs');
    const errorBox = $('#review-generation-error');
    const routes = {
        index: @json(route('bp.reviews.generations.index')),
        store: @json(route('bp.reviews.generations.store')),
        showTemplate: @json(route('bp.reviews.generations.show', ['run' => '__RUN__'])),
        fetchCategory: @json(route('backpack.helpers.fetch', ['key' => 'category'])),
        fetchBrand: @json(route('backpack.helpers.fetch', ['key' => 'brand'])),
        fetchProduct: @json(route('backpack.helpers.fetch', ['key' => 'product'])),
    };
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
                        <div class="text-muted small">${escapeHtml(run.summary || 'Генерация отзывов')}</div>
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
        $.get(routes.index, {limit: 10})
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
            $.get(routes.showTemplate.replace('__RUN__', String(runId)))
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
                        pagination: {
                            more: Boolean(data.next_page_url),
                        }
                    };
                }
            }
        });
    }

    function updateModeFields() {
        const mode = $('#review_generation_mode').val();
        $('.mode-field').addClass('d-none');
        $(`.mode-field[data-mode="${mode}"]`).removeClass('d-none');
    }

    function collectPayload() {
        return {
            selection_mode: $('#review_generation_mode').val(),
            category_id: $('#review_generation_category').val() || null,
            brand_id: $('#review_generation_brand').val() || null,
            product_ids: $('#review_generation_products').val() || [],
            review_count_max: $('#review_generation_review_count_max').val() || null,
            product_limit: $('#review_generation_product_limit').val() || null,
            min_reviews: $('#review_generation_min').val() || 5,
            max_reviews: $('#review_generation_max').val() || 20,
            locales: $('#review_generation_locales').val() || [],
            countries: $('#review_generation_countries').val() || [],
            skip_existing: $('#review_generation_skip_existing').is(':checked') ? 1 : 0,
            publish_now: $('#review_generation_publish_now').is(':checked') ? 1 : 0,
            schedule_start: $('#review_generation_schedule_start').val() || null,
            schedule_min_per_day: $('#review_generation_schedule_min_per_day').val() || null,
            schedule_max_per_day: $('#review_generation_schedule_max_per_day').val() || null,
            schedule_hour_from: $('#review_generation_schedule_hour_from').val() || null,
            schedule_hour_to: $('#review_generation_schedule_hour_to').val() || null,
            dry_run: $('#review_generation_dry_run').is(':checked') ? 1 : 0,
        };
    }

    $('#review_generation_mode').on('change', updateModeFields);

    if (typeof $.fn.select2 === 'function') {
        $('#review_generation_locales, #review_generation_countries').select2({dropdownParent: modal, width: '100%'});
        initAjaxSelect($('#review_generation_category'), routes.fetchCategory, false);
        initAjaxSelect($('#review_generation_brand'), routes.fetchBrand, false);
        initAjaxSelect($('#review_generation_products'), routes.fetchProduct, true);
    }

    $('#open-review-generation-modal').on('click', function() {
        modal.appendTo('body').modal('show');
        resetError();
        updateModeFields();
        fetchRuns();
    });

    $('#review-generation-refresh').on('click', fetchRuns);

    $('#submit-review-generation').on('click', function() {
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
            let message = xhr.responseJSON?.message || 'Не удалось запустить генерацию отзывов.';

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
