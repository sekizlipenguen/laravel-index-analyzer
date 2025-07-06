<?php

namespace SekizliPenguen\IndexAnalyzer;

use Illuminate\Support\ServiceProvider;
use SekizliPenguen\IndexAnalyzer\Http\Middleware\CaptureQueriesMiddleware;
use SekizliPenguen\IndexAnalyzer\Http\Middleware\LocaleMiddleware;

class IndexAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/index-analyzer.php', 'index-analyzer'
        );

        $this->app->singleton('index-analyzer', function ($app) {
            return new IndexAnalyzer($app);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/index-analyzer.php' => config_path('index-analyzer.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/laravel-index-analyzer'),
            ], 'public');
        }

        if (!$this->isEnabled()) {
            return;
        }

        // View dosyalarını yayınla
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/index-analyzer'),
        ], 'views');

        // Doğru namespace ile view dosyalarını yükle
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'index-analyzer');

        // Dil dosyalarını yükle
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'index-analyzer');
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/index-analyzer'),
        ], 'lang');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->app['router']->aliasMiddleware('capture-queries', CaptureQueriesMiddleware::class);
        $this->app['router']->aliasMiddleware('index-analyzer-locale', LocaleMiddleware::class);

        $this->injectAssets();
    }

    /**
     * Determine if the package is enabled.
     *
     * @return bool
     */
    protected function isEnabled(): bool
    {
        return config('index-analyzer.enabled', false) ||
            env('INDEX_ANALYZER_ENABLED', false);
    }

    /**
     * Inject assets into responses.
     *
     * @return void
     */
    protected function injectAssets()
    {
        if (!$this->app->runningInConsole() && $this->isEnabled()) {
            // DebugBar middleware'ini ekle
            $this->app->make('Illuminate\Contracts\Http\Kernel')
                ->pushMiddleware('SekizliPenguen\IndexAnalyzer\Http\Middleware\InjectDebugBarMiddleware');

            // Sorgu yakalama middleware'ini ekle
            $this->app->make('Illuminate\Contracts\Http\Kernel')
                ->pushMiddleware('SekizliPenguen\IndexAnalyzer\Http\Middleware\CaptureQueriesMiddleware');
        }
    }
}
