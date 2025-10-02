<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\Reviews\app\Http\Requests\ReviewRequest;
use Backpack\Reviews\app\Models\Admin\Review as AdminReview;

/**
 * Class ReviewCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ReviewCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation  { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;

    use \Backpack\Helpers\Traits\Admin\TreeListOperation;

    use \App\Http\Controllers\Admin\Traits\ReviewCrud;

    public function setup()
    {
      $this->crud->setModel(AdminReview::class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/review');
      $this->crud->setEntityNameStrings('отзыв', 'отзывы');

      $reviewable_types_list = \Settings::get('backpack.reviews.reviewable_types_list', []);

      $reviewable_options = [];
      foreach ($reviewable_types_list as $item) {
          $reviewable_options[$item['model']] = $item['name'];
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

      // TODO: remove setFromDb() and manually define Columns, maybe Filters
      
      // $this->crud->setFromDb();
              
      
      $this->crud->addColumn([
        'name' => 'created_at',
        'label' => 'Дата',
        'type'=>'datetime'
      ]);
      $this->crud->addColumn([
        'name' => 'is_moderated',
        'label' => 'Опубликован',
        'type' => 'toggle',
      ]);
      
      if(config('backpack.reviews.enable_review_type')) {
        $this->crud->addColumn([
          'name' => 'type',
          'label' => 'Тип',
        ]);
      }
    
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
      
      if(config('backpack.reviews.enable_rating')) {
        // $this->crud->addColumn([
        //   'name' => 'rating',
        //   'label' => '⭐',
        // ]);
        $this->crud->addColumn([
            'name'  => 'rating',        // поле в БД (число, например 3.4)
            'type'  => 'rating_stars',  // совпадает с именем blade-файла
            'label' => 'Рейтинг',
            'max'   => 5,               // максимально возможное значение (обязательно)
            // опционально:
            'color' => '#f2c200',       // цвет звёзд (золото/жёлтый)
            'size'  => '18px',          // размер иконок (например 16-20px)
            'show_value' => true,       // показывать всплывающую подсказку "X / max"
        ]);
      }

      $this->crud->addColumn([
        'name' => 'text',
        'label' => 'Текст'
      ]);

      $this->crud->addColumn([
        'name'         => 'reactions',          // аксессор вернёт массив
        'type'         => 'reactions',     // имя blade-файла
        'label'        => 'Реакции',
        'likes_key'    => 'likes',
        'dislikes_key' => 'dislikes',
        // визуальные опции:
        'compact'      => false,
        'show_total'   => true,                 // показать Σ и %
        'size'         => '18px',
        'likes_color'  => '#28a745',
        'dislikes_color' => '#dc3545',
        'thousand_sep' => ' ',
        // Если Backpack экранирует HTML, убедись что колонка не экранируется:
        'escaped'      => false,
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
        'attribute' => 'shortIdentity',
        'ajax' => true
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
        'data_source' => url("/admin/api/product"),
        'allows_null' => true,
        'attributes' => $attrs,
        'ajax' => true
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
          'attribute' => 'uniqString',
          'hint' => 'Cсылка на пользователя в системе'
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
                'type'  => 'browse',
                'label' => 'Фото автора',
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

      if(config('backpack.reviews.enable_review_type')) {
        $this->crud->addField([
          'name' => 'text',
          'label' => 'Сообщение/html-код видео',
          'type' => 'textarea',
          'attributes' => [
            'rows' => '8'
          ]
        ]);
      } else {
        $this->crud->addField([
          'name' => 'text',
          'label' => 'Сообщение',
          'type' => 'textarea',
          'attributes' => [
            'rows' => '8'
          ]
        ]);
      }

      $this->crud->addField([
        'name'  => 'separator_5',
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

      if($model_string === 'null')
        return null;
      else
        return $model_string;
    }

    private function getReviewableName() {
      if($this->getReviewableType())
        return $this->reviewableList[$this->getReviewableType()] ?? 'Запись';
      else
        return 'Запись';
    }

    // CHANGE THIS
    protected function fetchReviewable()
    {
        return $this->fetch([
          'model' => \Backpack\Store\app\Models\Product::class, // required
          'searchable_attributes' => ['name', 'code', 'slug'],
          'paginate' => 50
        ]);
    }


    protected function fetchParent()
    {
        return $this->fetch([
          'model' => Backpack\Reviews\app\Models\Review::class, // required
          'searchable_attributes' => ['id', 'text'],
          'paginate' => 50
        ]);
    }
    // public function update($request){
    //   $requestData = \Request::all();
    //   $requestData['http_referrer'] = 'https://google.com';

    //   $response = $this->traitUpdate();
    //   return $response;
    // }
}
