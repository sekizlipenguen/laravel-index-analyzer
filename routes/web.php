<?php

use Illuminate\Support\Facades\Route;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\DashboardController;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\IndexAnalyzerController;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\LanguageController;

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

    Route::get('/get-stats', [IndexAnalyzerController::class, 'getStats'])
        ->name('index-analyzer.get-stats');

    Route::post('/execute-statements', [IndexAnalyzerController::class, 'executeStatements'])
        ->name('index-analyzer.execute-statements');

    // Dil değiştirme rotaları - tüm yolları destekle
    Route::get('/languages', [LanguageController::class, 'getSupportedLanguages'])
        ->name('index-analyzer.languages');
    Route::post('/change-language', [LanguageController::class, 'changeLanguage'])
        ->name('index-analyzer.change-language');
    Route::post('/set-locale/{locale}', [LanguageController::class, 'setLocale'])
        ->name('index-analyzer.set-locale');
    Route::get('/set-locale/{locale}', [LanguageController::class, 'setLocale'])
        ->name('index-analyzer.get-locale');
    Route::get('/locale/{locale}', [LanguageController::class, 'setLocale'])
        ->name('index-analyzer.locale');
});
