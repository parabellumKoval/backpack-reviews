<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\Reviews\app\Http\Requests\ReviewRequest;
use Backpack\Reviews\app\Models\Review;

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

    use \App\Http\Controllers\Admin\Traits\ReviewCrud;

    public function setup()
    {
      $this->crud->setModel('Backpack\Reviews\app\Models\Review');
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/review');
      $this->crud->setEntityNameStrings('отзыв', 'отзывы');

      $this->reviewableList = config('backpack.reviews.reviewable_types_list', []);

      // CURRENT MODEL
      $this->setEntry();

      // if($this->crud->getCurrentOperation() === 'update' && \Request::query('reviewable_type')){
      //   $redirect_to = \Request::url();
      //   header("Location: {$redirect_to}");
      //   die();
      // }

      // Trait
      $this->setupOperation();
    }

    protected function setupShowOperation()
    {
    }
    
    protected function setupListOperation()
    {
      // TODO: remove setFromDb() and manually define Columns, maybe Filters
      
      // $this->crud->setFromDb();
              
      $this->crud->addColumn([
        'name' => 'is_moderated',
        'label' => '✅',
        'type' => 'check'
      ]);

      $this->crud->addColumn([
        'name' => 'photoAnyway',
        'label' => '📷',
        'type' => 'image',
        'height' => '50px',
        'width'  => '50px',
      ]);
      
      $this->crud->addColumn([
        'name' => 'created_at',
        'label' => '🗓'
      ]);
      
      if(config('backpack.reviews.enable_review_type')) {
        $this->crud->addColumn([
          'name' => 'type',
          'label' => 'Тип',
        ]);
      }
    
      if(config('backapck.reviews.owner_model')) {
        $this->crud->addColumn([
          'name' => 'user',
          'label' => 'Автор',
          'type' => 'relationship',
          'attribute' => 'email'
        ]);
      }
      
      if(config('backpack.reviews.enable_rating')) {
        $this->crud->addColumn([
          'name' => 'rating',
          'label' => '⭐',
        ]);
      }

      if(config('backpack.reviews.enable_likes')) {
        $this->crud->addColumn([
          'name' => 'likes',
          'label' => '👍',
        ]);
      }

      if(config('backpack.reviews.enable_likes')) {
        $this->crud->addColumn([
          'name' => 'dislikes',
          'label' => '👎',
        ]);
      }

      $this->crud->addColumn([
        'name' => 'text',
        'label' => 'Текст'
      ]);

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
        'attribute' => 'shortIdentity'
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
        'allows_null' => true,
        'attributes' => $attrs
      ]); 
        
      $this->crud->addField([
        'name'  => 'separator_2',
        'type'  => 'custom_html',
        'value' => '<hr>'
      ]);

      if(config('backpack.reviews.owner_model')) {
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

    // public function update($request){
    //   $requestData = \Request::all();
    //   $requestData['http_referrer'] = 'https://google.com';

    //   $response = $this->traitUpdate();
    //   return $response;
    // }
}
