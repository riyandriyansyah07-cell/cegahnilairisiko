<?php

namespace App\Providers;

use App\Services\ModelService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModelService::class);
    }

    public function boot(): void
    {
        //
    }
}
