<?php

namespace Backpack\Reviews\app\Models\Admin;

use Backpack\Reviews\app\Models\GoogleReview as BaseGoogleReview;

class GoogleReview extends BaseGoogleReview
{
    public function getSettingsButtonHtml()
    {
        return '<a href="'.url('admin/settings/reviews').'" class="btn btn-outline-dark">
                    <i class="la la-gear"></i> Настройки отзывов
                </a>';
    }
}
