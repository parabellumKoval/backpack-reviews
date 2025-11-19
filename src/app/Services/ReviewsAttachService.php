<?php

namespace Backpack\Reviews\app\Services;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;

class ReviewsAttachService
{
    public function attachToCrud(CrudPanel $crud, string $tab = 'Отзывы', array $options = []): void
    {
        $crud->addField([
            'name'  => 'reviews',
            'label' => trans('reviews::field.title'),
            'type'  => 'reviews',
            'tab'   => $tab,
            'wrapper' => ['class' => 'col-12 p-0'],
            'reviewable_type' => $options['reviewable_type'] ?? null,
        ]);
    }
}
