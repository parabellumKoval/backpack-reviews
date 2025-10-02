<?php

namespace Backpack\Reviews\app\Traits;

use Backpack\Reviews\Facades\Reviews;

trait HasReviewsTab
{
    protected function addReviewsTab(string $tab = 'Отзывы'): void
    {
        Reviews::attachToCrud($this->crud, $tab);
    }
}
