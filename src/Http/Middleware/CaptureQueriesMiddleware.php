<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use SekizliPenguen\IndexAnalyzer\Facades\IndexAnalyzer;

class CaptureQueriesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (config('index-analyzer.enabled', false)) {
            // Facade kullanımı
            IndexAnalyzer::startCapturing();

            // Log dosyasına bilgi ekle
            Log::info('IndexAnalyzer: Sorgu yakalama başlatıldı');
        }

        return $next($request);
    }
}
