<?php

namespace Backpack\Reviews;

use Illuminate\Support\Facades\View;

use Backpack\Reviews\Facades\Reviews;
use Backpack\Reviews\app\Services\ReviewsAttachService;

use Backpack\Reviews\app\Models\Review;
use Backpack\Reviews\app\Models\Admin\Review as ReviewAdmin;
use Backpack\Reviews\app\Observers\ReviewObserver;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{   
    public function boot()
    {
        // регистрируем Backpack field
        // \View::addNamespace('crud', base_path('vendor/backpack/crud/src/resources/views'));
        View::addNamespace('crud', [
            resource_path('views/vendor/backpack/crud'),
            __DIR__.'/resources/views/vendor/backpack/crud',
        ]);

        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'review');
    
	    // Migrations
	    $this->loadMigrationsFrom(__DIR__.'/database/migrations');
	    
	    // Routes
    	$this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
    	$this->loadRoutesFrom(__DIR__.'/routes/api/review.php');
    
		// Config
        $this->publishes([
            __DIR__ . '/config/reviews.php' => config_path('/backpack/reviews.php'),
        ], 'config');
        
        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views'),
        ], 'views');

        $this->publishes([
            __DIR__.'/database/migrations' => resource_path('database/migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/routes/backpack/routes.php' => resource_path('/routes/backpack/reviews/routes.php'),
            __DIR__.'/routes/api/review.php' => resource_path('/routes/backpack/reviews/api.php'),
        ], 'routes');


        Review::observe(ReviewObserver::class);
        ReviewAdmin::observe(ReviewObserver::class);
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/reviews.php', 'backpack.reviews');

        $this->app->singleton(ReviewsAttachService::class, function() {
            return new ReviewsAttachService();
        });

        // $this->app->bind('review', function () {
        //     return new Reviews();
        // });
    }
}
