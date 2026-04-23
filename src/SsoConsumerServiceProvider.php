<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer;

use Illuminate\Support\ServiceProvider;

class SsoConsumerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sso-consumer.php', 'sso-consumer');

        // Bind the SsoUserResolver contract from config so the consuming app
        // only has to declare the class name in config/sso-consumer.php.
        $this->app->bind(
            Contracts\SsoUserResolver::class,
            fn ($app) => $app->make(config('sso-consumer.resolver'))
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/sso.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sso-consumer');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'sso-consumer');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sso-consumer.php' => config_path('sso-consumer.php'),
            ], 'sso-consumer-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sso-consumer'),
            ], 'sso-consumer-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/sso-consumer'),
            ], 'sso-consumer-lang');

            $this->commands([
                Console\CheckConfigCommand::class,
            ]);
        }
    }
}
