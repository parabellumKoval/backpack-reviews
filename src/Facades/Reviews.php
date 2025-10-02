<?php

namespace Backpack\Reviews\Facades;

use Illuminate\Support\Facades\Facade;
use Backpack\Reviews\app\Services\ReviewsAttachService;

/**
 * @method static void attachToCrud(\Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud, string $tab = 'Отзывы')
 */
class Reviews extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ReviewsAttachService::class;
    }
}