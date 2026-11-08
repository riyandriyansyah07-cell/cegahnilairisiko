<?php

namespace App\Providers;

use App\Mail\Transport\AppsScriptTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Mail::extend('appsscript', fn (array $config) => new AppsScriptTransport(
            (string) ($config['endpoint'] ?? ''),
            (string) ($config['secret'] ?? ''),
        ));
    }
}