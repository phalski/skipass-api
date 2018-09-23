<?php

namespace App\Providers;

use App\SkipassService;
use App\SkipassServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SkipassServiceInterface::class, function($app) {
            return new SkipassService('http://kv.skipass.cx', 'en', 'Europe/Berlin', new \DateInterval('P6M'));
        });
    }
}
