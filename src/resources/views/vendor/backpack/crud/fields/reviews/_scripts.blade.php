@push('crud_fields_scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const root = document.querySelector('.bp-reviews');
  if (!root) return;

  const $list = root.querySelector('.js-reviews-list');
  const csrf = '{{ csrf_token() }}';
  const ownerSelectEl = root.querySelector('.js-owner-select');
  const ownerGuestBlock = root.querySelector('.js-owner-guest');
  const ownerProfileBlock = root.querySelector('.js-owner-profile');
  const ownerModeInputs = root.querySelectorAll('input[name="owner_mode"]');
  let reviewCache = new Map();

  const urls = {
    index: root.dataset.indexUrl,
    store: root.dataset.storeUrl,
    replyT: root.dataset.replyUrlTemplate,
    updateT: root.dataset.updateUrlTemplate,
    deleteT: root.dataset.deleteUrlTemplate,
    moderateT: root.dataset.moderateUrlTemplate,
    likeT: root.dataset.likeUrlTemplate,
    dislikeT: root.dataset.dislikeUrlTemplate,
    owners: root.dataset.ownersUrl || ''
  };
  const rtype = root.dataset.reviewableType;
  const rid   = root.dataset.reviewableId;

  const i18n = {
    textRequired: @json(trans('reviews::field.text_required')),
    replyTextRequired: @json(trans('reviews::field.reply_text_required')),
    ownerRequired: @json(trans('reviews::field.owner_required')),
    errorCreate: @json(trans('reviews::field.error_create')),
    verifiedBadge: @json(trans('reviews::field.verified_purchase_badge')),
    advantages: @json(trans('reviews::field.advantages')),
    flaws: @json(trans('reviews::field.flaws')),
    moderated: @json(trans('reviews::field.moderated')),
    moderatedYes: @json(trans('reviews::field.moderated_yes')),
    moderatedNo: @json(trans('reviews::field.moderated_no')),
    rating: @json(trans('reviews::field.rating')),
    likes: @json(trans('reviews::field.likes')),
    dislikes: @json(trans('reviews::field.dislikes')),
    ownerUnknown: @json(trans('reviews::field.owner_unknown')),
    replyPlaceholder: @json(trans('reviews::field.reply_placeholder'))
  };

  const safeReplaceId = (tpl, id) => tpl.replace(/\/0(?=\/|$)/, '/' + encodeURIComponent(id));
  const escapeHtml = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
  const el = (html) => { const d=document.createElement('div'); d.innerHTML=html.trim(); return d.firstChild; };

  const formatOwner = (owner) => {
    if (!owner) return '';
    const name = owner.name || owner.email || owner.phone || '';
    const email = owner.email && owner.email !== name ? owner.email : '';
    const pieces = [name, email].filter(Boolean);
    return pieces.length ? pieces.join(' • ') : i18n.ownerUnknown;
  };

  const getOwnerInitial = (owner) => {
    if (!owner) return '?';
    const source = (owner.name || owner.email || owner.phone || '').trim();
    return source ? source.charAt(0).toUpperCase() : '?';
  };

  const resolveOwnerPhoto = (owner) => {
    if (!owner || typeof owner !== 'object') return null;
    return owner.photo || owner.photo_url || owner.avatar || owner.avatar_url || null;
  };

  const renderAvatar = (owner) => {
    const photo = resolveOwnerPhoto(owner);
    if (photo) {
      return `<img class="review-avatar" src="${escapeHtml(photo)}" alt="">`;
    }
    return `<div class="review-avatar review-avatar--placeholder">${escapeHtml(getOwnerInitial(owner))}</div>`;
  };

  const btns = (r) => `
    <div class="review-actions mt-2">
      <button type="button" class="btn btn-sm btn-light js-reply" data-id="${r.id}">
        <i class="la la-reply"></i> @lang('reviews::field.reply')
      </button>
      <button type="button" class="btn btn-sm btn-light js-edit" data-id="${r.id}">
        <i class="la la-edit"></i> @lang('reviews::field.edit')
      </button>
      <button type="button" class="btn btn-sm btn-light js-moderate" data-id="${r.id}">
        <i class="la la-shield"></i> @lang('reviews::field.moderate')
      </button>
      @if(config('backpack.reviews.enable_likes'))
      <button type="button" class="btn btn-sm btn-light js-like" data-id="${r.id}">
        <i class="la la-thumbs-up"></i> ${r.likes}
      </button>
      <button type="button" class="btn btn-sm btn-light js-dislike" data-id="${r.id}">
        <i class="la la-thumbs-down"></i> ${r.dislikes}
      </button>
      @endif
      <button type="button" class="btn btn-sm btn-danger js-delete" data-id="${r.id}">
        <i class="la la-trash"></i> @lang('reviews::field.delete')
      </button>
    </div>`;

  const item = (r) => {
    const ownerStr = r.owner ? `<span class="review-owner">${escapeHtml(formatOwner(r.owner))}</span>` : '';
    const verified = r.verified_purchase ? `<span class="review-badge"><i class="la la-check"></i>${i18n.verifiedBadge}</span>` : '';
    const pros = r.advantages ? `<div class="review-pros"><strong>${i18n.advantages}</strong>${escapeHtml(r.advantages)}</div>` : '';
    const cons = r.flaws ? `<div class="review-cons"><strong>${i18n.flaws}</strong>${escapeHtml(r.flaws)}</div>` : '';
    const ratingValue = r.rating !== null && r.rating !== undefined && r.rating !== '' ? r.rating : '—';
    const moderated = r.is_moderated ? i18n.moderatedYes : i18n.moderatedNo;

    return `
    <div class="review-item" data-id="${r.id}">
      <div class="review-header">
        <div class="review-avatar-wrapper">${renderAvatar(r.owner)}</div>
        <div class="flex-grow-1">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <div>
              <strong>#${r.id}</strong>
              ${ownerStr}
              ${verified}
            </div>
            <small class="text-muted">${escapeHtml(r.created_at ?? '')}</small>
          </div>
          <div class="review-text">${escapeHtml(r.text || '')}</div>
          ${pros}
          ${cons}
          <div class="review-meta-line mt-2">
            <span>${i18n.rating}: ${escapeHtml(ratingValue)}</span>
            <span>${i18n.moderated}: ${moderated}</span>
            <span>${i18n.likes}: ${Number(r.likes ?? 0)}</span>
            <span>${i18n.dislikes}: ${Number(r.dislikes ?? 0)}</span>
          </div>
          ${btns(r)}
          <div class="js-children"></div>
        </div>
      </div>
    </div>`;
  };

  const asTree = (rows) => {
    if (!Array.isArray(rows)) return [];
    const byId = {}, roots = [];
    rows.forEach(r => {
      if (r && r.id) {
        byId[r.id] = {...r, children: []};
      }
    });
    rows.forEach(r => {
      if (r && r.id) {
        if (r.parent_id && byId[r.parent_id]) {
          byId[r.parent_id].children.push(byId[r.id]);
        } else {
          roots.push(byId[r.id]);
        }
      }
    });
    return roots;
  };

  const renderTree = (nodes, $parent) => {
    nodes.forEach(n => {
      const $node = el(item(n));
      $parent.appendChild($node);
      if(n.children && n.children.length) {
        renderTree(n.children, $node.querySelector('.js-children'));
      }
    });
  };

  function load() {
    $list.innerHTML = '<div class="text-muted">@lang('reviews::field.loading')</div>';
    fetch(urls.index, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => {
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
      })
      .then(response => {
        $list.innerHTML = '';
        const data = Array.isArray(response.data) ? response.data : [];
        reviewCache = new Map(data.map(entry => [String(entry.id), entry]));
        renderTree(asTree(data), $list);
      })
      .catch(error => {
        console.error('Error loading reviews:', error);
        $list.innerHTML = '<div class="text-danger">@lang('reviews::field.error_loading')</div>';
      });
  }

  const refreshBtn = root.querySelector('.js-refresh-reviews');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', function(e){
      e.preventDefault();
      load();
    });
  }

  const getOwnerMode = () => {
    const input = root.querySelector('input[name="owner_mode"]:checked');
    return input ? input.value : 'profile';
  };
  const $ownerSelect = (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && $.fn.select2 && ownerSelectEl) ? $(ownerSelectEl) : null;

  function updateOwnerBlocks() {
    const mode = getOwnerMode();
    if (mode === 'guest') {
      if (ownerGuestBlock) ownerGuestBlock.classList.remove('d-none');
      if (ownerProfileBlock) ownerProfileBlock.classList.add('d-none');
    } else {
      if (ownerProfileBlock) ownerProfileBlock.classList.remove('d-none');
      if (ownerGuestBlock) ownerGuestBlock.classList.add('d-none');
    }
  }
  ownerModeInputs.forEach(input => input.addEventListener('change', updateOwnerBlocks));

  function initOwnerSelect() {
    if (!$ownerSelect || !urls.owners) return;
    const placeholder = ownerSelectEl && ownerSelectEl.dataset ? ownerSelectEl.dataset.placeholder || '' : '';
    $ownerSelect.select2({
      theme: 'bootstrap',
      allowClear: true,
      placeholder: placeholder,
      ajax: {
        url: urls.owners,
        dataType: 'json',
        delay: 250,
        data: params => ({ q: params.term }),
        processResults: response => ({
          results: (response.data || []).map(item => ({
            id: item.id,
            text: item.text || item.name || ('#' + item.id)
          }))
        })
      }
    });
  }
  initOwnerSelect();
  updateOwnerBlocks();

  function getOwnerSelectValue() {
    if ($ownerSelect) {
      return $ownerSelect.val();
    }
    return ownerSelectEl && ownerSelectEl.value ? ownerSelectEl.value : '';
  }

  function resetOwnerSelect() {
    if ($ownerSelect) {
      $ownerSelect.val(null).trigger('change');
    } else if (ownerSelectEl) {
      ownerSelectEl.value = '';
    }
  }

  function resetCreateForm() {
    const wrap = root.querySelector('.js-create-review');
    if (!wrap) return;
    wrap.querySelector('textarea[name="text"]').value = '';
    wrap.querySelector('select[name="rating"]').value = '';
    wrap.querySelector('input[name="is_moderated"]').checked = true;
    wrap.querySelector('input[name="verified_purchase"]').checked = false;
    wrap.querySelector('textarea[name="advantages"]').value = '';
    wrap.querySelector('textarea[name="flaws"]').value = '';
    wrap.querySelector('input[name="likes"]').value = '0';
    wrap.querySelector('input[name="dislikes"]').value = '0';
    wrap.querySelector('input[name="guest_name"]').value = '';
    wrap.querySelector('input[name="guest_email"]').value = '';
    wrap.querySelector('input[name="guest_phone"]').value = '';
    resetOwnerSelect();
    ownerModeInputs.forEach(input => input.checked = input.value === 'profile');
    updateOwnerBlocks();
  }

  function collectCreatePayload() {
    const wrap = root.querySelector('.js-create-review');
    if (!wrap) return null;

    const text = wrap.querySelector('textarea[name="text"]').value.trim();
    if (!text) {
      alert(i18n.textRequired);
      return null;
    }

    const payload = {
      reviewable_type: rtype,
      reviewable_id: rid,
      text: text,
      rating: wrap.querySelector('select[name="rating"]').value || '',
      is_moderated: wrap.querySelector('input[name="is_moderated"]').checked ? 1 : 0,
      verified_purchase: wrap.querySelector('input[name="verified_purchase"]').checked ? 1 : 0,
      advantages: wrap.querySelector('textarea[name="advantages"]').value.trim(),
      flaws: wrap.querySelector('textarea[name="flaws"]').value.trim(),
      likes: wrap.querySelector('input[name="likes"]').value || '0',
      dislikes: wrap.querySelector('input[name="dislikes"]').value || '0',
    };

    const ownerMode = getOwnerMode();
    payload.owner_mode = ownerMode;

    if (ownerMode === 'profile') {
      const ownerId = getOwnerSelectValue();
      if (!ownerId) {
        alert(i18n.ownerRequired);
        return null;
      }
      payload.owner_id = ownerId;
    } else if (ownerMode === 'guest') {
      payload.guest_name = wrap.querySelector('input[name="guest_name"]').value.trim();
      payload.guest_email = wrap.querySelector('input[name="guest_email"]').value.trim();
      payload.guest_phone = wrap.querySelector('input[name="guest_phone"]').value.trim();
    }

    return payload;
  }

  function submitCreate(payload) {
    const fd = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      if (typeof value === 'string' && value === '') return;
      fd.append(key, value);
    });

    fetch(urls.store, {
      method:'POST',
      headers:{
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: fd
    })
      .then(r => r.ok ? r.json() : Promise.reject())
      .then(() => {
        resetCreateForm();
        load();
      })
      .catch(()=>alert(i18n.errorCreate));
  }

  const createBtn = root.querySelector('.js-create-btn');
  if (createBtn) {
    createBtn.addEventListener('click', function(e){
      e.preventDefault();
      const payload = collectCreatePayload();
      if (!payload) return;
      submitCreate(payload);
    });
  }

  $list.addEventListener('click', function(e){
    const btn = e.target.closest('button'); if(!btn) return;
    e.preventDefault();
    const id  = btn.dataset.id;

    if(btn.classList.contains('js-delete')) {
      if(!confirm('@lang('reviews::field.delete_confirm')')) return;
      fetch(safeReplaceId(urls.deleteT, id), {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With':'XMLHttpRequest'
        },
        body: new URLSearchParams({'_method':'DELETE'})
      })
        .then(()=>load());
      return;
    }

    if(btn.classList.contains('js-moderate')) {
      fetch(safeReplaceId(urls.moderateT, id), {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With':'XMLHttpRequest'
        }
      })
        .then(()=>load());
      return;
    }

    if(btn.classList.contains('js-like')) {
      fetch(safeReplaceId(urls.likeT, id), {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With':'XMLHttpRequest'
        }
      })
        .then(()=>load());
      return;
    }

    if(btn.classList.contains('js-dislike')) {
      fetch(safeReplaceId(urls.dislikeT, id), {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With':'XMLHttpRequest'
        }
      })
        .then(()=>load());
      return;
    }

    function closeAllForms() {
      $list.querySelectorAll('.js-reply-block, .js-edit-block').forEach(form => form.remove());
    }

    if(btn.classList.contains('js-reply')) {
      const $wrap = btn.closest('.review-item');
      const exist = $wrap.querySelector('.js-reply-block');
      if(exist) { exist.remove(); return; }
      closeAllForms();
      const $block = el(`
        <div class="js-reply-block mt-2">
          <div class="form-group">
            <textarea class="form-control js-reply-text" rows="2" placeholder="${escapeHtml(i18n.replyPlaceholder)}"></textarea>
          </div>
          <div class="form-group">
            <select class="form-control js-reply-rating">
              <option value="">—</option>
              @for($i=1; $i<=5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
            </select>
          </div>
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input js-reply-moderated" id="isModeratedReply${id}" checked>
            <label class="form-check-label" for="isModeratedReply${id}">@lang('reviews::field.moderated')</label>
          </div>
          <button type="button" class="btn btn-sm btn-primary js-reply-send" data-id="${id}">
            @lang('reviews::field.reply_send')
          </button>
        </div>`);
      $wrap.appendChild($block);
      return;
    }

    if(btn.classList.contains('js-edit')) {
      const $wrap = btn.closest('.review-item');
      const existingForm = $wrap.querySelector('.js-edit-block');
      if(existingForm) {
        existingForm.remove();
        return;
      }
      closeAllForms();
      const currentData = reviewCache.get(String(id)) || {};
      const $blk = el(`
        <div class="js-edit-block mt-2">
          <div class="form-group">
            <textarea class="form-control js-edit-text" rows="3">${escapeHtml(currentData.text || '')}</textarea>
          </div>
          <div class="form-group">
            <label>@lang('reviews::field.rating')</label>
            <select class="form-control js-edit-rating">
              <option value="">—</option>
              @for($i=1; $i<=5; $i++)
              <option value="{{ $i }}" ${Number(currentData.rating ?? 0) === {{ $i }} ? 'selected' : ''}>{{ $i }}</option>
              @endfor
            </select>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label>@lang('reviews::field.advantages')</label>
              <textarea class="form-control js-edit-advantages" rows="2">${escapeHtml(currentData.advantages || '')}</textarea>
            </div>
            <div class="form-group col-md-6">
              <label>@lang('reviews::field.flaws')</label>
              <textarea class="form-control js-edit-flaws" rows="2">${escapeHtml(currentData.flaws || '')}</textarea>
            </div>
          </div>
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input js-edit-verified" id="editVerified${id}" ${currentData.verified_purchase ? 'checked' : ''}>
            <label class="form-check-label" for="editVerified${id}">@lang('reviews::field.verified_purchase')</label>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label>@lang('reviews::field.likes')</label>
              <input type="number" class="form-control js-edit-likes" min="0" step="1" value="${Number(currentData.likes ?? 0)}">
            </div>
            <div class="form-group col-md-6">
              <label>@lang('reviews::field.dislikes')</label>
              <input type="number" class="form-control js-edit-dislikes" min="0" step="1" value="${Number(currentData.dislikes ?? 0)}">
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-primary js-edit-save" data-id="${id}">
            @lang('reviews::field.save')
          </button>
        </div>`);
      $wrap.appendChild($blk);
      return;
    }
  });

  $list.addEventListener('click', function(e){
    const btn = e.target.closest('.js-reply-send, .js-edit-save'); if(!btn) return;
    e.preventDefault();
    const id = btn.dataset.id;

    if(btn.classList.contains('js-reply-send')) {
      const block = btn.closest('.js-reply-block');
      const text = block.querySelector('.js-reply-text').value.trim();
      const rating = block.querySelector('.js-reply-rating').value || '';
      const moderated = block.querySelector('.js-reply-moderated').checked ? 1 : 0;

      if(!text){
        alert(i18n.replyTextRequired);
        return;
      }

      const fd = new FormData();
      fd.append('text', text);
      if(rating) fd.append('rating', rating);
      fd.append('is_moderated', moderated);

      fetch(safeReplaceId(urls.replyT, id), {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With':'XMLHttpRequest'
        },
        body: fd
      })
        .then(()=>load());
      return;
    }

    if(btn.classList.contains('js-edit-save')) {
      const block = btn.closest('.js-edit-block');
      const text = block.querySelector('.js-edit-text').value.trim();
      const rating = block.querySelector('.js-edit-rating').value || '';
      const advantages = block.querySelector('.js-edit-advantages').value.trim();
      const flaws = block.querySelector('.js-edit-flaws').value.trim();
      const verified = block.querySelector('.js-edit-verified').checked ? 1 : 0;
      const likes = block.querySelector('.js-edit-likes').value || '0';
      const dislikes = block.querySelector('.js-edit-dislikes').value || '0';

      if(!text){
        alert(i18n.textRequired);
        return;
      }

      const fd = new FormData();
      fd.append('_method', 'PATCH');
      fd.append('text', text);
      if(rating) fd.append('rating', rating);
      fd.append('advantages', advantages);
      fd.append('flaws', flaws);
      fd.append('verified_purchase', verified);
      fd.append('likes', likes);
      fd.append('dislikes', dislikes);

      fetch(safeReplaceId(urls.updateT, id), {
        method:'POST',
        headers:{
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With':'XMLHttpRequest'
        },
        body: fd
      })
        .then(()=>load());
      return;
    }
  });

  load();
});
</script>
@endpush
