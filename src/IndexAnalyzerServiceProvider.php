<?php

namespace SekizliPenguen\IndexAnalyzer;

use Illuminate\Support\ServiceProvider;
use SekizliPenguen\IndexAnalyzer\Console\DetectMissingIndexes;

class IndexAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register()
    {

        $this->app->singleton('index-analyzer', function ($app) {
            return new IndexAnalyzer;
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/index-analyzer.php', 'index-analyzer');
    }

    /**
     * Perform post-registration booting of services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/index-analyzer.php' => config_path('index-analyzer.php'),
            ], 'index-analyzer-config');

            $this->commands([
                DetectMissingIndexes::class,
            ]);
        }
    }
}
