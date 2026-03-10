@php
    $route = route('bp.reviews.google.sync');
@endphp

<form method="POST" action="{{ $route }}" style="display:inline" id="google-reviews-sync-form">
    @csrf
</form>

<a href="javascript:void(0)" onclick="confirmGoogleReviewsSync()" class="btn btn-primary">
    <i class="lab la-google"></i>
    Синхронизировать Google отзывы
</a>

@push('after_scripts')
<script>
    function confirmGoogleReviewsSync() {
        swal({
            title: "Синхронизация Google отзывов",
            text: "Запустить выгрузку отзывов из Google Business Profile?",
            icon: "info",
            buttons: {
                cancel: {
                    text: "{!! trans('backpack::base.cancel') !!}",
                    value: null,
                    visible: true,
                    className: "bg-secondary",
                    closeModal: true,
                },
                confirm: {
                    text: "{!! trans('backpack::crud.yes') !!}",
                    value: true,
                    visible: true,
                    className: "bg-primary",
                }
            },
        }).then((value) => {
            if (value) {
                document.getElementById('google-reviews-sync-form').submit();
            }
        });
    }
</script>
@endpush
