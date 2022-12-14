<?php

namespace Backpack\Reviews\Facades;

use Illuminate\Support\Facades\Facade;

class Review extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'review';
    }
}
