<?php

use Illuminate\Support\Facades\Route;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\DashboardController;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\IndexAnalyzerController;

$routePrefix = config('index-analyzer.route_prefix', 'index-analyzer');
$middleware = config('index-analyzer.middleware', ['web']);

Route::group([
    'prefix' => $routePrefix,
    'middleware' => $middleware,
], function () {
    // Ana kontrol paneli sayfası
    Route::get('/', [DashboardController::class, 'index'])
        ->name('index-analyzer.dashboard');

    // API endpoint'leri
    Route::post('/start-crawl', [IndexAnalyzerController::class, 'startCrawl'])
        ->name('index-analyzer.start-crawl');

    Route::post('/generate-suggestions', [IndexAnalyzerController::class, 'generateSuggestions'])
        ->name('index-analyzer.generate-suggestions');

    Route::post('/record-query', [IndexAnalyzerController::class, 'recordQuery'])
        ->name('index-analyzer.record-query');

    Route::post('/clear-queries', [IndexAnalyzerController::class, 'clearQueries'])
        ->name('index-analyzer.clear-queries');
});
