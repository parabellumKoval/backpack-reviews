<?php

namespace Backpack\Reviews\app\Models\Admin;

use Backpack\Reviews\app\Models\Review as BaseReview;

class Review extends BaseReview
{   
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */
	
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

         
    public function getMorphClass()
    {
        return 'Backpack\Reviews\app\Models\Review';
    } 

    public function getSettingsButtonHtml()
    {
      return '<a href="'.url('admin/settings/reviews').'" class="btn btn-outline-dark">
                            <i class="la la-gear"></i> Настройки отзывов
                        </a>';
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function getEnabledDetailsRowAttribute() {
      return $this->children()->exists();
    }
}
