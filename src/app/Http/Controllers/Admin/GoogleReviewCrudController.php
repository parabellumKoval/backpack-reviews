<?php

namespace Backpack\Reviews\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Reviews\app\Models\Admin\GoogleReview;

class GoogleReviewCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel(GoogleReview::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/google-review');
        $this->crud->setEntityNameStrings('Google отзыв', 'Google отзывы');

        $this->crud->denyAccess(['create', 'update', 'delete']);
    }

    protected function setupListOperation()
    {
        $this->crud->addButtonFromModelFunction('top', 'reviews_settings', 'getSettingsButtonHtml', 'end');

        $this->crud->addColumn([
            'name' => 'review_created_at',
            'label' => 'Дата',
            'type' => 'datetime',
        ]);

        $this->crud->addColumn([
            'name' => 'rating',
            'label' => 'Оценка',
            'type' => 'number',
        ]);

        $this->crud->addColumn([
            'name' => 'reviewer_name',
            'label' => 'Автор',
            'type' => 'text',
        ]);

        $this->crud->addColumn([
            'name' => 'comment',
            'label' => 'Отзыв',
            'type' => 'text',
            'limit' => 120,
        ]);

        $this->crud->addColumn([
            'name' => 'location',
            'label' => 'Локация',
            'type' => 'relationship',
            'attribute' => 'title',
        ]);

        $this->crud->addColumn([
            'name' => 'synced_at',
            'label' => 'Синхронизация',
            'type' => 'datetime',
        ]);
    }

    protected function setupShowOperation()
    {
        CRUD::setFromDb();
    }
}
