<?php

use Illuminate\Support\Facades\Route;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\IndexAnalyzerController;
use SekizliPenguen\IndexAnalyzer\Http\Controllers\LanguageController;

Route::prefix(config('index-analyzer.route_prefix', 'index-analyzer'))->middleware(['web'])->group(function () {
    Route::get('/', function () {
        return view('index-analyzer.dashboard');
    });

    // API Endpoints
    Route::post('/start-crawl', [IndexAnalyzerController::class, 'startCrawl']);
    Route::post('/generate-suggestions', [IndexAnalyzerController::class, 'generateSuggestions']);
    Route::post('/record-query', [IndexAnalyzerController::class, 'recordQuery']);
    Route::post('/clear-queries', [IndexAnalyzerController::class, 'clearQueries']);

    // Language Routes
    Route::get('/languages', [LanguageController::class, 'getSupportedLanguages']);
    Route::post('/change-language', [LanguageController::class, 'changeLanguage']);
});
