<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for MySQL compatibility
        Schema::defaultStringLength(191);
        
        // Prevent lazy loading in development
        Model::preventLazyLoading(! app()->isProduction());
        
        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        
        // Prevent accessing missing attributes
        Model::preventAccessingMissingAttributes(! app()->isProduction());
        
        // Register model observers for cache invalidation
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Category::observe(\App\Observers\CategoryObserver::class);
        \App\Models\Brand::observe(\App\Observers\BrandObserver::class);
    }
}