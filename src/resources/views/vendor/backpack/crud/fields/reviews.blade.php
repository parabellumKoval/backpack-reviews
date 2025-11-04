@php
  $entry = $crud->getCurrentEntry();
  $reviewableType = $entry->getMorphClass();
  $reviewableId   = $entry->getKey();

  $indexUrl    = route('bp.reviews.index',  ['type' => $reviewableType, 'id' => $reviewableId]);
  $storeUrl    = route('bp.reviews.store');
  $replyUrl    = route('bp.reviews.reply', ['review' => 0]);
  $updateUrl   = route('bp.reviews.update', ['review' => 0]);
  $deleteUrl   = route('bp.reviews.destroy', ['review' => 0]);
  $moderateUrl = route('bp.reviews.moderate', ['review' => 0]);
  $likeUrl     = route('bp.reviews.like', ['review' => 0]);
  $dislikeUrl  = route('bp.reviews.dislike', ['review' => 0]);
@endphp

<div
  class="bp-reviews col-sm-12"
  data-index-url="{{ $indexUrl }}"
  data-store-url="{{ $storeUrl }}"
  data-reply-url-template="{{ $replyUrl }}"
  data-update-url-template="{{ $updateUrl }}"
  data-delete-url-template="{{ $deleteUrl }}"
  data-moderate-url-template="{{ $moderateUrl }}"
  data-like-url-template="{{ $likeUrl }}"
  data-dislike-url-template="{{ $dislikeUrl }}"
  data-reviewable-type="{{ $reviewableType }}"
  data-reviewable-id="{{ $reviewableId }}"
>
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="la la-comments"></i> @lang('review::field.title')</span>
      <button type="button" class="btn btn-sm btn-secondary js-refresh-reviews">
        @lang('review::field.refresh')
      </button>
    </div>
    <div class="card-body">
      @include('crud::fields.reviews._form')
      {{-- Список отзывов --}}
      <div class="js-reviews-list"></div>
    </div>
  </div>
</div>

@include('crud::fields.reviews._styles')
@include('crud::fields.reviews._scripts')


