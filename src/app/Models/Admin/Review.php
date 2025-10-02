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

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */   
    public function getOwnerAttribute() {
      return isset($this->extras['owner'])? [$this->extras['owner']]: null;
    }

    public function getEnabledDetailsRowAttribute() {
      return $this->children()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    public function setExtrasAttribute($value) {
      $extras = $value;
      $owner_array = json_decode($value['owner'], true);

      $extras['owner'] = !empty($owner_array)? $owner_array[0]: null;
      
      $this->attributes['extras'] = json_encode($extras);
    }

}
