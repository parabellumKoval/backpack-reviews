{{-- Форма добавления (НЕ <form>, чтобы не было вложений) --}}
<div class="js-create-review mb-3">
  <div class="row">
    <div class="form-group col-md-8">
      <label class="w-100">@lang('reviews::field.new_review')</label>
      <textarea name="text" class="form-control" rows="4" required placeholder="@lang('reviews::field.text_placeholder')"></textarea>
    </div>
    <div class="col-md-4">
      <div class="form-group">
        <label>@lang('reviews::field.rating')</label>
        <select name="rating" class="form-control">
          <option value="">—</option>
          @for($i=1; $i<=5; $i++)
            <option value="{{ $i }}">{{ $i }}</option>
          @endfor
        </select>
      </div>
      <div class="form-check mb-2">
        <input type="checkbox" name="is_moderated" class="form-check-input" id="isModeratedCreate" checked>
        <label for="isModeratedCreate" class="form-check-label">@lang('reviews::field.moderated')</label>
      </div>
      <div class="form-check mb-3">
        <input type="checkbox" name="verified_purchase" class="form-check-input" id="verifiedPurchaseCreate">
        <label for="verifiedPurchaseCreate" class="form-check-label">@lang('reviews::field.verified_purchase')</label>
      </div>
    </div>
  </div>

  <div class="form-group">
    <label class="d-block">@lang('reviews::field.author_mode_label')</label>
    <div class="d-flex flex-wrap gap-3">
      <div class="form-check form-check-inline">
        <input type="radio" name="owner_mode" class="form-check-input" id="ownerModeProfile" value="profile" checked>
        <label class="form-check-label" for="ownerModeProfile">@lang('reviews::field.author_mode_profile')</label>
      </div>
      <div class="form-check form-check-inline">
        <input type="radio" name="owner_mode" class="form-check-input" id="ownerModeGuest" value="guest">
        <label class="form-check-label" for="ownerModeGuest">@lang('reviews::field.author_mode_guest')</label>
      </div>
    </div>
  </div>

  <div class="form-group js-owner-profile">
    <label>@lang('reviews::field.author_select')</label>
    <select class="form-control js-owner-select" data-placeholder="@lang('reviews::field.author_select_placeholder')"></select>
    <small class="form-text text-muted">@lang('reviews::field.author_select_help')</small>
  </div>

  <div class="row js-owner-guest d-none">
    <div class="form-group col-md-4">
      <label>@lang('reviews::field.guest_name')</label>
      <input type="text" name="guest_name" class="form-control" placeholder="@lang('reviews::field.guest_name_placeholder')">
    </div>
    <div class="form-group col-md-4">
      <label>@lang('reviews::field.guest_email')</label>
      <input type="email" name="guest_email" class="form-control" placeholder="@lang('reviews::field.guest_email_placeholder')">
    </div>
    <div class="form-group col-md-4">
      <label>@lang('reviews::field.guest_phone')</label>
      <input type="text" name="guest_phone" class="form-control" placeholder="@lang('reviews::field.guest_phone_placeholder')">
    </div>
  </div>

  <div class="row">
    <div class="form-group col-md-6">
      <label>@lang('reviews::field.advantages')</label>
      <textarea name="advantages" class="form-control" rows="2" placeholder="@lang('reviews::field.advantages_placeholder')"></textarea>
    </div>
    <div class="form-group col-md-6">
      <label>@lang('reviews::field.flaws')</label>
      <textarea name="flaws" class="form-control" rows="2" placeholder="@lang('reviews::field.flaws_placeholder')"></textarea>
    </div>
  </div>

  <div class="row">
    <div class="form-group col-md-6">
      <label>@lang('reviews::field.likes')</label>
      <input type="number" name="likes" class="form-control" min="0" step="1" value="0">
    </div>
    <div class="form-group col-md-6">
      <label>@lang('reviews::field.dislikes')</label>
      <input type="number" name="dislikes" class="form-control" min="0" step="1" value="0">
    </div>
  </div>

  <button type="button" class="btn btn-primary js-create-btn">
    @lang('reviews::field.add')
  </button>
</div>
