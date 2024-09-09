<?php

namespace DavidMaximous\Fawaterak;

use DavidMaximous\Fawaterak\Classes\FawaterakPayment;
use DavidMaximous\Fawaterak\Classes\FawaterakVerify;
use Illuminate\Support\ServiceProvider;

class FawaterakServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->configure();

        $langPath = __DIR__ . '/../resources/lang';

        $this->registerPublishing($langPath);

        $this->loadTranslationsFrom($langPath, 'fawaterak');

        $this->publishes([
            __DIR__ . '/../config/fawaterak.php' => config_path('fawaterak.php'),
            $langPath => resource_path('lang/vendor/fawaterak'),
        ], 'fawaterak-all');
    }

    public function register()
    {
        $this->app->bind(FawaterakPayment::class, function () {
            return new FawaterakPayment();
        });
        $this->app->bind(FawaterakVerify::class, function () {
            return new FawaterakVerify();
        });
    }

    protected function configure()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/fawaterak.php',
            'fawaterak'
        );
    }

    protected function registerPublishing($langPath)
    {
        $this->publishes([
            __DIR__ . '/../config/fawaterak.php' => config_path('fawaterak.php'),
        ], 'fawaterak-config');

        $this->publishes([
            $langPath => resource_path('lang/vendor/fawaterak'),
        ], 'fawaterak-lang');
    }
}
