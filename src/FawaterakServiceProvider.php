<?php

namespace DavidMaximous\Fawaterak;

use DavidMaximous\Fawaterak\Classes\FawaterakAuth;
use DavidMaximous\Fawaterak\Classes\FawaterakClient;
use DavidMaximous\Fawaterak\Classes\FawaterakPayment;
use DavidMaximous\Fawaterak\Classes\FawaterakRefund;
use DavidMaximous\Fawaterak\Classes\FawaterakTokenization;
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
        $this->app->singleton(FawaterakClient::class, function () {
            return new FawaterakClient();
        });

        $this->app->bind(FawaterakAuth::class, function ($app) {
            return $app->make(FawaterakClient::class)->auth();
        });

        $this->app->bind(FawaterakPayment::class, function ($app) {
            return new FawaterakPayment($app->make(FawaterakClient::class));
        });

        $this->app->bind(FawaterakVerify::class, function ($app) {
            return new FawaterakVerify($app->make(FawaterakClient::class));
        });

        $this->app->bind(FawaterakTokenization::class, function ($app) {
            return new FawaterakTokenization($app->make(FawaterakClient::class));
        });

        $this->app->bind(FawaterakRefund::class, function ($app) {
            return new FawaterakRefund($app->make(FawaterakClient::class));
        });

        // Accessor used by the Fawaterak facade.
        $this->app->bind('fawaterak', function ($app) {
            return $app->make(FawaterakPayment::class);
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
