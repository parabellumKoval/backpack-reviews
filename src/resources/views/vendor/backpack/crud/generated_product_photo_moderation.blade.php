@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid d-flex justify-content-between align-items-center">
    <h2 class="mb-2">Модерация пользовательских фото товаров</h2>
    <a href="{{ backpack_url('generated-product-photo') }}" class="btn btn-outline-secondary">Вернуться к списку</a>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong>Партия: {{ $batch->count() }} фото</strong>
                        <div class="text-muted small">Осталось в очереди после этой партии: {{ $remaining }}</div>
                    </div>
                    <div>
                        <a href="{{ request()->fullUrlWithQuery(['batch' => $batchSize]) }}" class="btn btn-sm btn-outline-secondary">Обновить</a>
                    </div>
                </div>

                @if($batch->isEmpty())
                    <div class="alert alert-success mb-0">Непроверенных фото больше нет.</div>
                @else
                    <form id="moderation-form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="row" id="photo-moderation-grid">
                            @foreach($batch as $photo)
                                @php
                                    $imageUrl = $photo->image_url;
                                    $product = $photo->product;
                                @endphp
                                <div class="col-md-6 col-lg-4 mb-4" data-photo-id="{{ $photo->id }}">
                                    <div class="border rounded p-2 h-100 d-flex flex-column">
                                        <div class="mb-2 text-muted small">#{{ $photo->id }}</div>
                                        <div class="mb-2" style="height: 500px; display:flex; align-items:center; justify-content:center; background:#f8f9fa; border-radius:6px; overflow:hidden;">
                                            @if($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="Generated photo" style="max-width:100%; max-height:500px; object-fit:contain;">
                                            @else
                                                <span class="text-muted">Без изображения</span>
                                            @endif
                                        </div>
                                        <div class="small mb-2" style="min-height: 52px;">
                                            <div><strong>Товар:</strong> {{ $product->name ?? ('#' . $photo->product_id) }}</div>
                                            <div class="text-muted">ID товара: {{ $photo->product_id }}</div>
                                        </div>
                                        <button type="button" class="btn btn-outline-danger btn-sm mt-auto js-toggle-delete" data-photo-id="{{ $photo->id }}">Удалить</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small" id="moderation-selection-hint">Отмечено к удалению: 0</div>
                            <button type="submit" class="btn btn-primary" id="moderation-submit">Одобрить</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
(function () {
    const form = document.getElementById('moderation-form');
    if (!form) {
        return;
    }

    const submitUrl = @json($submitUrl);
    const deleteIds = new Set();
    const displayedIds = Array.from(document.querySelectorAll('[data-photo-id]')).map(el => Number(el.getAttribute('data-photo-id'))).filter(Boolean);
    const hint = document.getElementById('moderation-selection-hint');

    function updateHint() {
        if (!hint) return;
        hint.textContent = `Отмечено к удалению: ${deleteIds.size}`;
    }

    function setButtonState(button, active) {
        if (!button) return;

        if (active) {
            button.classList.remove('btn-outline-danger');
            button.classList.add('btn-danger');
            button.textContent = 'Отменить удаление';
            return;
        }

        button.classList.add('btn-outline-danger');
        button.classList.remove('btn-danger');
        button.textContent = 'Удалить';
    }

    document.querySelectorAll('.js-toggle-delete').forEach((button) => {
        button.addEventListener('click', () => {
            const id = Number(button.getAttribute('data-photo-id'));
            if (!id) return;

            if (deleteIds.has(id)) {
                deleteIds.delete(id);
                setButtonState(button, false);
            } else {
                deleteIds.add(id);
                setButtonState(button, true);
            }

            updateHint();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = document.getElementById('moderation-submit');
        if (submitButton) {
            submitButton.disabled = true;
        }

        const body = {
            displayed_ids: displayedIds,
            delete_ids: Array.from(deleteIds),
            _token: form.querySelector('input[name="_token"]').value,
        };

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                throw new Error('Moderation request failed');
            }

            window.location.reload();
        } catch (error) {
            if (submitButton) {
                submitButton.disabled = false;
            }
            alert('Не удалось применить модерацию. Попробуйте еще раз.');
        }
    });

    updateHint();
})();
</script>
@endpush
