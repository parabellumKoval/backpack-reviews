<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use App\Support\ReviewRewardContext;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\Reviews\app\Http\Requests\ReviewRequest;
use Backpack\Reviews\app\Models\Admin\Review as AdminReview;

use ParabellumKoval\BackpackImages\Traits\HasImagesCrudComponents;

/**
 * Class ReviewCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ReviewCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;

    use \Backpack\Helpers\Traits\Admin\TreeListOperation;
    use \Backpack\Helpers\Traits\Admin\HasToggleColumns;
    use \App\Http\Controllers\Admin\Traits\ReviewCrud;

    use HasImagesCrudComponents;

    protected array $reviewableDefinitions = [];

    public function setup()
    {
      $this->crud->setModel(AdminReview::class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/review');
      $this->crud->setEntityNameStrings('отзыв', 'отзывы');

      $reviewable_types_list = \Settings::get('backpack.reviews.reviewable_types_list', []);

      $reviewable_options = [];
      $this->reviewableDefinitions = [];

      foreach ($reviewable_types_list as $item) {
          $modelClass = $this->normalizeReviewableClass($item['model'] ?? null);
          $name = $item['name'] ?? null;

          if (!$modelClass || !$name) {
              continue;
          }

          $reviewable_options[$modelClass] = $name;
          $item['model'] = $modelClass;
          $this->reviewableDefinitions[$modelClass] = $item;
      }
      $this->reviewableList = $reviewable_options;
      
      // CURRENT MODEL
      $this->setEntry();

      // if($this->crud->getCurrentOperation() === 'update' && \Request::query('reviewable_type')){
      //   $redirect_to = \Request::url();
      //   header("Location: {$redirect_to}");
      //   die();
      // }

      // Trait
      $this->setupOperation();

      
      $this->setupTreeList([
        'title' => 'Ответы к отзыву' 
      ]); 
    }

    public function store()
    {
        $context = app(ReviewRewardContext::class);
        $context->skipRewards(request()->boolean('skip_reward', true));

        try {
            return $this->traitStore();
        } finally {
            $context->skipRewards(false);
        }
    }

    public function update()
    {
        $context = app(ReviewRewardContext::class);
        $context->skipRewards(request()->boolean('skip_reward', false));

        try {
            return $this->traitUpdate();
        } finally {
            $context->skipRewards(false);
        }
    }


    /**
     * Backpack вызовет этот метод для details_row.
     * Вернёт HTML со всеми дочерними элементами (таблица без пагинации/сортировки).
     */
    public function showDetailsRow($id)
    {
        return $this->showDetailsRowTrait($id);
    }

    protected function setupShowOperation()
    {
    }
    
    protected function setupListOperation()
    {

      $this->crud->addButtonFromModelFunction('top', 'reviews_settings', 'getSettingsButtonHtml', 'end');

      $this->crud->addFilter([
        'name'  => 'is_video',
        'type'  => 'dropdown',
        'label' => 'Видео отзыв',
      ], [
        '1' => 'Только видео',
        '0' => 'Без видео',
      ], function ($value) {
        if ($value === '1') {
          $this->crud->addClause('where', 'is_video', true);
        } else {
          $this->crud->addClause('where', function ($query) {
            $query->where('is_video', false)->orWhereNull('is_video');
          });
        }
      });

      // TODO: remove setFromDb() and manually define Columns, maybe Filters
      
      // $this->crud->setFromDb();
              
      
      $this->crud->addColumn([
        'name' => 'created_at',
        'label' => 'Дата',
        'type'=>'datetime'
      ]);
      $this->addToggleColumn([
        'name' => 'is_moderated',
        'label' => '<i class="la la-eye"></i>',
        'toggle' => [
            'values' => [
                'checked' => 1,
                'unchecked' => 0,
            ],
        ],
      ]);

      if(config('backpack.reviews.owner_model', null)) {
        // $this->crud->addColumn([
        //   'name' => 'user',
        //   'label' => 'Автор',
        //   'type' => 'relationship',
        //   'attribute' => 'email'
        // ]);
        $this->crud->addColumn([
            'name'       => 'owner_id',
            'label'      => 'Автор',
            'type'       => 'user_card',
            'user_model' => \App\Models\User::class,
        ]);
      }

      $this->crud->addColumn([
        'name' => 'country',
        'label' => 'Страна',
        'type' => 'flag',
      ]);

      $this->crud->addColumn([
        'name' => 'lang',
        'label' => 'Язык',
      ]);

      $this->crud->addColumn([
        'name' => 'is_video',
        'label' => 'Тип',
        'type' => 'view',
        'view' => 'reviews::columns.review_type',
        'show_text' => true,
        'icon_size' => '20px',
      ]);

      // Reviewable entity card (Product, Article, etc.)
      $this->crud->addColumn([
        'name' => 'reviewable',
        'label' => 'Связанная запись',
        'type' => 'view',
        'view' => 'reviews::columns.reviewable',
        'escaped' => false,
      ]);
      
      if(config('backpack.reviews.enable_review_type')) {
        $this->crud->addColumn([
          'name' => 'type',
          'label' => 'Тип',
        ]);
      }

      // Combined column: Review text with reactions below
      $this->crud->addColumn([
        'name' => 'text_with_reactions',
        'label' => 'Отзыв',
        'type' => 'view',
        'view' => 'reviews::columns.review_text_with_reactions',
        'text_limit' => 150,
        'likes_key' => 'likes',
        'dislikes_key' => 'dislikes',
        'compact' => true,
        'show_total' => true,
        'reactions_size' => '14px',
        'likes_color' => '#28a745',
        'dislikes_color' => '#dc3545',
        'thousand_sep' => ' ',
        'escaped' => false,
      ]);


      // if(config('backpack.reviews.enable_likes')) {
      //   $this->crud->addColumn([
      //     'name' => 'likes',
      //     'label' => '👍',
      //   ]);
      // }

      // if(config('backpack.reviews.enable_likes')) {
      //   $this->crud->addColumn([
      //     'name' => 'dislikes',
      //     'label' => '👎',
      //   ]);
      // }

      // Trait
      $this->listOperation();
    }

    protected function setupCreateOperation()
    {
       $this->crud->setValidation(ReviewRequest::class);
        // allow inputs rendered inside conditional_fields to reach the model
        $this->crud->setOperationSetting('saveAllInputsExcept', [
            '_token',
            '_method',
            'http_referrer',
            'current_tab',
            'save_action',
        ]);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
      
        
      $this->crud->addField([
        'name' => 'is_moderated',
        'label' => 'Опубликовано',
        'type' => 'boolean',
        'default' => 1
      ]);
      
      $this->crud->addField([
        'name' => 'parent',
        'label' => 'Родительский комментарий',
        'type' => 'relationship',
        'attribute' => 'uniqHtml',
        'ajax' => true,
        'data_source' => route('backpack.helpers.fetch', ['key' => 'review']),
        'minimum_input_length' => 2,
      ]);

      // $this->crud->addField([
      //   'name'  => 'separator_0',
      //   'type'  => 'custom_html',
      //   'value' => '<hr>'
      // ]);

      $js_attributes = [
        'data-value' => '',
        'onfocus' => "this.setAttribute('data-value', this.value);",
        'onchange' => "
            const value = event.target.value
            let isConfirmed = confirm('Несохраненные данные будут сброшены. Все равно продолжить?');
            
            if(isConfirmed) {
              reload_page(event);
            } else{
              this.value = this.getAttribute('data-value');
            }

            function reload_page(event) {
              const value = event.target.value
              url = insertParam('reviewable_type', value)
            };

            function insertParam(key, value) {
              key = encodeURIComponent(key);
              value = encodeURIComponent(value);
          
              // kvp looks like ['key1=value1', 'key2=value2', ...]
              var kvp = document.location.search.substr(1).split('&');
              let i=0;
          
              for(; i<kvp.length; i++){
                  if (kvp[i].startsWith(key + '=')) {
                      let pair = kvp[i].split('=');
                      pair[1] = value;
                      kvp[i] = pair.join('=');
                      break;
                  }
              }
          
              if(i >= kvp.length){
                  kvp[kvp.length] = [key,value].join('=');
              }
          
              // can return this or...
              let params = kvp.join('&');
          
              // reload page with new params
              document.location.search = params;
          }
          "
      ];

      $this->crud->addField([
        'name'  => 'separator_1',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);

      $this->crud->addField([
        'name'  => 'caption_0',
        'type'  => 'custom_html',
        'value' => '<h5>Связанные данные</h5>'
      ]);

      $this->crud->addField([
        'name' => 'reviewable_type',
        'label' => 'Тип связанной модели',
        'type' => 'select_from_array',
        'options' => $this->reviewableList,
        'value' => $this->getReviewableType(),
        'attributes' => $js_attributes,
        'allows_null' => true,
        'default' => null,
      ]);

      if(!$this->getReviewableTypeModel()) {
        $attrs = [
          'disabled' => 'disabled'
        ];
      }else {
        $attrs = [];
      }

      $this->crud->addField([
        'name' => 'reviewable_id',
        'label' => $this->getReviewableName(),
        'type' => "relationship",
        'model' => $this->getReviewableTypeModel(),
        'attribute' => 'uniqHtml',
        'data_source' => $this->getReviewableFetchRoute() ?? url("/admin/api/product"),
        'allows_null' => true,
        'attributes' => $attrs,
        'ajax' => true,
        'key_column' => $this->getReviewableKeyColumn(),
      ]); 

      $this->crud->addField([
        'name' => 'lang',
        'label' => 'Язык',
        'type' => 'text',
        'wrapper' => ['class' => 'form-group col-md-2'],
        'attributes' => [
          'maxlength' => 5,
          'placeholder' => 'uk',
        ],
        'hint' => 'ISO 639-1 код (напр. uk, en)',
      ]);

      $this->crud->addField([
        'name' => 'country',
        'label' => 'Страна',
        'type' => 'text',
        'wrapper' => ['class' => 'form-group col-md-2'],
        'attributes' => [
          'maxlength' => 2,
          'placeholder' => 'UA',
          'style' => 'text-transform:uppercase',
        ],
        'hint' => 'ISO 3166-1 Alpha-2 (UA, CZ, ...)',
      ]);

      $this->crud->addField([
        'name'  => 'separator_2',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);

      if(config('backpack.reviews.owner_model', null)) {
        $this->crud->addField([
          'name' => 'user',
          'label' => 'Автор',
          'type' => 'relationship',
          'model' => config('backpack.reviews.owner_model'),
          // Should be implemented in owner model
          'attribute' => 'uniqHtml',
          'hint' => 'Cсылка на пользователя в системе',
          'ajax' => true,
          'data_source' => route('backpack.helpers.fetch', ['key' => 'user']),
          'minimum_input_length' => 0,
        ]);
      }

      $this->crud->addField([
        'name'  => 'separator_3',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);

      $this->crud->addField([
        'name'  => 'caption_1',
        'type'  => 'custom_html',
        'value' => '<h5>Автор (статические данные)</h5>'
      ]);
      
      //
      // $this->crud->addField([
      //   'name'    => 'owner[id]',
      //   'type'    => 'text',
      //   'label'   => 'Id автора',
      //   'wrapper' => ['class' => 'form-group col-md-2'],
      //   // 'fake' => true,
      //   // 'store_in' => 'extras'
      // ]);

      // $this->crud->addField([
      //   'name'    => 'ownerGullname',
      //   'type'    => 'text',
      //   'label'   => 'Имя автора',
      //   'wrapper' => ['class' => 'form-group col-md-5'],
      //   // 'fake' => true,
      //   // 'store_in' => 'extras'
      // ]);

      // $this->crud->addField([
      //   'name'    => 'extrasOwnerEmail',
      //   'type'    => 'text',
      //   'label'   => 'Email автора',
      //   'wrapper' => ['class' => 'form-group col-md-5'],
      //   // 'fake' => true,
      //   // 'store_in' => 'extras'
      // ]);

      // $this->crud->addField([
      //   'name'  => 'extrasOwnerPhoto',
      //   'type'  => 'browse',
      //   'label' => 'Фото автора',
      //   // 'fake' => true,
      //   // 'store_in' => 'extras'
      // ]);

      $this->crud->addField([
          'name' => 'owner',
          'label' => 'Автор',
          'type'  => 'repeatable',
          'fake' => true,
          'store_in' => 'extras',
          'fields' => [
            [
                'name'    => 'id',
                'type'    => 'text',
                'label'   => 'Id автора',
                'wrapper' => ['class' => 'form-group col-md-2'],
            ],
            [
                'name'    => 'name',
                'type'    => 'text',
                'label'   => 'Имя автора',
                'wrapper' => ['class' => 'form-group col-md-5'],
            ],
            [
                'name'    => 'email',
                'type'    => 'text',
                'label'   => 'Email автора',
                'wrapper' => ['class' => 'form-group col-md-5'],
            ],
            [
                'name'  => 'photo',
                'type'  => 'image',
                'label' => 'Фото автора',
                'crop' => false,
                'aspect_ratio' => 1,
            ],
        ],
        'new_item_label'  => 'Добавить',
        'init_rows' => 1,
        'min_rows' => 1,
        'max_rows' => 1,
      ]);

      $this->crud->addField([
        'name'  => 'separator_4',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);
        
      if(config('backpack.reviews.enable_rating')) {
        $this->crud->addField([
          'name' => 'rating',
          'label' => 'Оценка',
          'type' => 'number',
          'attributes' => [
            'max' => '5',
            'min' => '0'
          ],
          'wrapper' => [ 
            'class' => 'form-group col-md-4'
          ]
        ]);
      }

      $this->crud->addField([
        'name'  => 'separator_5',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);

      $this->crud->addField([
        'name'  => 'caption_video',
        'type'  => 'custom_html',
        'value' => '<h5>Тип отзыва</h5>'
      ]);

      $videoPosterField = array_replace_recursive(
        AdminReview::imageFieldDefinition('video_poster'),
        [
          'name' => 'video_poster',
          'hint' => 'Изображение-обложка для видеоролика',
        ]
      );

      $this->crud->addField([
        'name' => 'content_blocks',
        'type' => 'conditional_fields',
        'driver' => [
          'name' => 'is_video',
          'label' => 'Видео отзыв',
          'type' => 'boolean',
          'default' => $this->entry? (int) $this->entry->is_video : 0,
          'hint' => 'Переключатель определяет, какие поля нужно заполнить.',
        ],
        'branches' => [
          '0' => [
            'fields' => [
              [
                'name' => 'text',
                'label' => config('backpack.reviews.enable_review_type')
                  ? 'Сообщение/html-код видео'
                  : 'Сообщение',
                'type' => 'textarea',
                'attributes' => [
                  'rows' => '8',
                ],
              ],
            ],
          ],
          '1' => [
            'fields' => [
              [
                'name' => 'video_url',
                'label' => 'Ссылка на видео (embed)',
                'type' => 'url',
                'attributes' => [
                  'placeholder' => 'https://www.youtube.com/embed/...',
                ],
                'hint' => 'Используйте embed-ссылку, например https://www.youtube.com/embed/dQw4w9WgXcQ',
              ],
              [
                'name' => 'video_title',
                'label' => 'Заголовок видео',
                'type' => 'text',
                'translatable' => true,
                'attributes' => [
                  'maxlength' => 255,
                ],
              ],
              $videoPosterField,
              [
                'name' => 'text',
                'label' => 'Комментарий (необязательно)',
                'type' => 'textarea',
                'attributes' => [
                  'rows' => '6',
                ],
              ],
            ],
          ],
        ],
      ]);

      $this->crud->addField([
        'name'  => 'skip_reward_toggle',
        'type'  => 'custom_html',
        'value' => $this->renderSkipRewardToggle(),
      ]);

      $this->crud->addField([
        'name'  => 'separator_6',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);

      $this->crud->addField([
        'name'  => 'caption_2',
        'type'  => 'custom_html',
        'value' => '<h5>Данные сгенерированные пользователями</h5>'
      ]);

      $this->crud->addField([
        'name' => 'likes',
        'label' => 'Лайки',
        'type' => 'number',
        'default' => 0,
        'attributes' => [
          'min' => 0
        ],
        'wrapper' => [ 
          'class' => 'form-group col-md-4'
        ]
      ]);

      $this->crud->addField([
        'name' => 'dislikes',
        'label' => 'Дизлайки',
        'type' => 'number',
        'default' => 0,
        'attributes' => [
          'min' => 0
        ],
        'wrapper' => [ 
          'class' => 'form-group col-md-4'
        ]
      ]);

      // Trait
      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function renderSkipRewardToggle(): string
    {
        $isCreate = $this->crud->getCurrentOperation() === 'create';
        $default = $isCreate ? 1 : 0;
        $checked = old('skip_reward', $default) ? 'checked' : '';
        $id = 'skip_reward_toggle';

        return <<<HTML
<div class="form-group">
  <div class="form-check">
    <input type="hidden" name="skip_reward" value="0">
    <input type="checkbox" class="form-check-input" id="{$id}" name="skip_reward" value="1" {$checked}>
    <label class="form-check-label" for="{$id}">Не начислять вознаграждение за этот отзыв</label>
  </div>
  <p class="text-muted small mb-0">Когда включено, пользователю не будут начислены бонусы за этот отзыв.</p>
</div>
HTML;
    }

    private function setEntry() {
      if($this->crud->getCurrentOperation() === 'update')
        $this->entry = $this->crud->getEntry(\Route::current()->parameter('id'));
      else
        $this->entry = null;
    }

    private function getReviewableType() {
      $reviewable_type = \Request::get('reviewable_type');

      if(\Request::has('reviewable_type')){
        return $reviewable_type? $reviewable_type: 'null';
      } elseif($this->entry && $this->entry->reviewable_type){
        return $this->entry->reviewable_type;
      } else {
        return 'null';
      }
    }

    private function getReviewableTypeModel() {
      $model_string = $this->getReviewableType();

      if($model_string === 'null' || !$model_string)
        return null;

      return $this->normalizeReviewableClass($model_string);
    }

    private function getReviewableFetchRoute()
    {
        $model = $this->getReviewableTypeModel();

        if (!$model) {
            return null;
        }

        $definition = $this->getReviewableDefinition($model);

        if (!empty($definition['fetch_helper_key'])) {
            return route('backpack.helpers.fetch', ['key' => $definition['fetch_helper_key']]);
        }

        $helperKey = helper_fetch_key_for_model($model);

        if (!$helperKey) {
            return null;
        }

        return route('backpack.helpers.fetch', ['key' => $helperKey]);
    }

    private function getReviewableName() {
      $type = $this->getReviewableType();

      if(!$type || $type === 'null') {
        return 'Запись';
      }

      return $this->reviewableList[$type] ?? $this->reviewableList[$this->normalizeReviewableClass($type)] ?? 'Запись';
    }

    private function getReviewableDefinition(?string $model = null): array
    {
        $resolvedModel = $this->normalizeReviewableClass($model ?? $this->getReviewableTypeModel());

        if (!$resolvedModel) {
            return [];
        }

        return $this->reviewableDefinitions[$resolvedModel] ?? [];
    }

    private function getReviewableKeyColumn(): string
    {
        $model = $this->getReviewableTypeModel();
        $definition = $this->getReviewableDefinition($model);

        if ($model && class_exists($model)) {
            $default = (new $model())->getKeyName();
        } else {
            $default = 'id';
        }

        return $definition['reviewable_key'] ?? $default;
    }

    private function normalizeReviewableClass(?string $class): ?string
    {
        if (!$class) {
            return null;
        }

        $normalized = ltrim($class, '\\');

        $map = [
            \Backpack\Store\app\Models\Catalog::class => \App\Models\Product::class,
            \Backpack\Store\app\Models\Product::class => \App\Models\Product::class,
        ];

        return $map[$normalized] ?? $normalized;
    }

    // CHANGE THIS
    // protected function fetchReviewable()
    // {
    //     return $this->fetch([
    //       'model' => \Backpack\Store\app\Models\Product::class, // required
    //       'searchable_attributes' => ['name', 'code', 'slug'],
    //       'paginate' => 50
    //     ]);
    // }


    // protected function fetchParent()
    // {
    //     return $this->fetch([
    //       'model' => Backpack\Reviews\app\Models\Review::class, // required
    //       'searchable_attributes' => ['id', 'text'],
    //       'paginate' => 50
    //     ]);
    // }

    // public function update($request){
    //   $requestData = \Request::all();
    //   $requestData['http_referrer'] = 'https://google.com';

    //   $response = $this->traitUpdate();
    //   return $response;
    // }
}
