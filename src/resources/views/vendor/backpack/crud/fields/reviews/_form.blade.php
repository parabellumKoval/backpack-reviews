{{-- Форма добавления (НЕ <form>, чтобы не было вложений) --}}
<div class="js-create-review mb-3">
  <div class="form-group">
    <label class="w-100">@lang('review::field.new_review')</label>
    <textarea name="text" class="form-control" rows="3" required placeholder="Текст отзыва"></textarea>
  </div>
  <div class="form-group">
    <label>@lang('review::field.rating')</label>
    <select name="rating" class="form-control">
      <option value="">—</option>
      @for($i=1; $i<=5; $i++)
        <option value="{{ $i }}">{{ $i }}</option>
      @endfor
    </select>
  </div>
  <div class="form-check mb-2">
    <input type="checkbox" name="is_moderated" class="form-check-input" id="isModeratedCreate" checked>
    <label for="isModeratedCreate" class="form-check-label">@lang('review::field.moderated')</label>
  </div>
  <button type="button" class="btn btn-primary js-create-btn">
    @lang('review::field.add')
  </button>
</div>
