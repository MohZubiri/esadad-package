<?php

namespace ESadad\PaymentGateway\Providers;

use Illuminate\Support\ServiceProvider;
use ESadad\PaymentGateway\Services\ESadadSoapService;
use ESadad\PaymentGateway\ESadad;

class ESadadServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../Config/esadad.php', 'esadad'
        );

        // Register SOAP service
        $this->app->singleton(ESadadSoapService::class, function ($app) {
            return new ESadadSoapService();
        });

        // Register main service
        $this->app->singleton('esadad', function ($app) {
            return new ESadad(
                $app->make(ESadadSoapService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../Config/esadad.php' => config_path('esadad.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../Migrations/' => database_path('migrations'),
        ], 'migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add any commands here if needed
            ]);
        }
    }
}
