@push('crud_fields_scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const root = document.querySelector('.bp-reviews');
  if (!root) return;
  
  const $list = root.querySelector('.js-reviews-list');
  const csrf = '{{ csrf_token() }}';

  const urls = {
    index: root.dataset.indexUrl,
    store: root.dataset.storeUrl,
    replyT: root.dataset.replyUrlTemplate,
    updateT: root.dataset.updateUrlTemplate,
    deleteT: root.dataset.deleteUrlTemplate,
    moderateT: root.dataset.moderateUrlTemplate,
    likeT: root.dataset.likeUrlTemplate,
    dislikeT: root.dataset.dislikeUrlTemplate,
  };
  const rtype = root.dataset.reviewableType;
  const rid   = root.dataset.reviewableId;

  const safeReplaceId = (tpl, id) => tpl.replace(/\/0(?=\/|$)/, '/' + encodeURIComponent(id));

  const el = (html) => { const d=document.createElement('div'); d.innerHTML=html.trim(); return d.firstChild; };

  const btns = (r) => `
    <div class="review-actions mt-2">
      <button type="button" class="btn btn-sm btn-light js-reply" data-id="${r.id}">
        <i class="la la-reply"></i> @lang('review::field.reply')
      </button>
      <button type="button" class="btn btn-sm btn-light js-edit" data-id="${r.id}">
        <i class="la la-edit"></i> @lang('review::field.edit')
      </button>
      <button type="button" class="btn btn-sm btn-light js-moderate" data-id="${r.id}">
        <i class="la la-shield"></i> @lang('review::field.moderate')
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
        <i class="la la-trash"></i> @lang('review::field.delete')
      </button>
    </div>`;

  const item = (r) => `
    <div class="review-item" data-id="${r.id}">
      <div><strong>#${r.id}</strong> — <span>${(r.text || '').replace(/</g,'&lt;')}</span></div>
      <div class="review-meta">
        @lang('review::field.rating'): ${r.rating ?? '—'}
        • @lang('review::field.moderated'): ${r.is_moderated ? '✓' : '—'}
        • ${r.created_at ?? ''}
      </div>
      ${btns(r)}
      <div class="js-children"></div>
    </div>`;

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
      if(n.children?.length) renderTree(n.children, $node.querySelector('.js-children'));
    });
  };

  function load() {
    $list.innerHTML = '<div class="text-muted">@lang('review::field.loading')</div>';
    fetch(urls.index, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(r => {
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
      })
      .then(response => {
        $list.innerHTML = '';
        const data = response.data || [];
        renderTree(asTree(data), $list);
      })
      .catch(error => {
        console.error('Error loading reviews:', error);
        $list.innerHTML = '<div class="text-danger">@lang('review::field.error_loading')</div>';
      });
  }

  // кнопка Refresh
  root.querySelector('.js-refresh-reviews')?.addEventListener('click', function(e){
    e.preventDefault();
    load();
  });

  // создание root-отзыва
  root.querySelector('.js-create-btn')?.addEventListener('click', function(e){
    e.preventDefault();
    const wrap = root.querySelector('.js-create-review');
    const text = wrap.querySelector('textarea[name="text"]').value.trim();
    const rating = wrap.querySelector('select[name="rating"]').value || '';
    const moderated = wrap.querySelector('input[name="is_moderated"]').checked ? 1 : 0;

    if(!text){ alert('Введите текст отзыва'); return; }

    const fd = new FormData();
    fd.append('reviewable_type', rtype);
    fd.append('reviewable_id', rid);
    fd.append('text', text);
    if(rating) fd.append('rating', rating);
    fd.append('is_moderated', moderated);

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
        wrap.querySelector('textarea[name="text"]').value = '';
        wrap.querySelector('select[name="rating"]').value = '';
        wrap.querySelector('input[name="is_moderated"]').checked = true;
        load();
      })
      .catch(()=>alert('Ошибка при создании отзыва'));
  });

  // делегирование действий по списку
  $list.addEventListener('click', function(e){
    const btn = e.target.closest('button'); if(!btn) return;
    e.preventDefault();
    const id  = btn.dataset.id;

    if(btn.classList.contains('js-delete')) {
      if(!confirm('Удалить отзыв и его ответы?')) return;
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

    // Функция для закрытия всех открытых форм в дереве отзывов
    function closeAllForms() {
      const forms = $list.querySelectorAll('.js-reply-block, .js-edit-block');
      forms.forEach(form => form.remove());
    }

    if(btn.classList.contains('js-reply')) {
      const $wrap = btn.closest('.review-item');
      const exist = $wrap.querySelector('.js-reply-block');
      
      // Если форма уже открыта - закрываем её
      if(exist) {
        exist.remove();
        return;
      }
      
      // Закрываем все открытые формы перед открытием новой
      closeAllForms();
      
      const $block = el(`
        <div class="js-reply-block mt-2">
          <div class="form-group">
            <textarea class="form-control js-reply-text" rows="2" placeholder="Ответ..."></textarea>
          </div>
          <div class="form-group">
            <select class="form-control js-reply-rating">
              <option value="">—</option>
              @for($i=1; $i<=5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
            </select>
          </div>
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input js-reply-moderated" id="isModeratedReply${id}" checked>
            <label class="form-check-label" for="isModeratedReply${id}">@lang('review::field.moderated')</label>
          </div>
          <button type="button" class="btn btn-sm btn-primary js-reply-send" data-id="${id}">
            @lang('review::field.reply_send')
          </button>
        </div>`);
      $wrap.appendChild($block);
      return;
    }

    if(btn.classList.contains('js-edit')) {
      const $wrap = btn.closest('.review-item');
      const existingForm = $wrap.querySelector('.js-edit-block');
      
      // Если форма уже открыта - закрываем её
      if(existingForm) {
        existingForm.remove();
        return;
      }
      
      // Закрываем все открытые формы перед открытием новой
      closeAllForms();
      
      const current = $wrap.querySelector('span').textContent;
      const currentRating = $wrap.querySelector('.review-meta').textContent.match(/rating: (\d+|—)/i)?.[1] || '';
      
      const $blk = el(`
        <div class="js-edit-block mt-2">
          <div class="form-group">
            <textarea class="form-control js-edit-text" rows="2">${current}</textarea>
          </div>
          <div class="form-group">
            <label>@lang('review::field.rating')</label>
            <select class="form-control js-edit-rating">
              <option value="">—</option>
              @for($i=1; $i<=5; $i++)
              <option value="{{ $i }}" ${currentRating == {{ $i }} ? 'selected' : ''}>{{ $i }}</option>
              @endfor
            </select>
          </div>
          <button type="button" class="btn btn-sm btn-primary js-edit-save" data-id="${id}">
            @lang('review::field.save')
          </button>
        </div>`);
      $wrap.appendChild($blk);
      return;
    }
  });

  // делегирование отправки reply/edit
  $list.addEventListener('click', function(e){
    const btn = e.target.closest('.js-reply-send, .js-edit-save'); if(!btn) return;
    e.preventDefault();
    const id = btn.dataset.id;

    if(btn.classList.contains('js-reply-send')) {
      const block = btn.closest('.js-reply-block');
      const text = block.querySelector('.js-reply-text').value.trim();
      const rating = block.querySelector('.js-reply-rating').value || '';
      const moderated = block.querySelector('.js-reply-moderated').checked ? 1 : 0;

      if(!text){ alert('Введите текст ответа'); return; }

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

      if(!text){ alert('Введите текст'); return; }

      const fd = new FormData();
      fd.append('_method', 'PATCH');
      fd.append('text', text);
      if(rating) fd.append('rating', rating);

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

  // первоначальная загрузка
  load();
});
</script>
@endpush
