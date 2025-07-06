<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SekizliPenguen\IndexAnalyzer\Facades\IndexAnalyzer;

class CaptureQueriesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (config('index-analyzer.enabled', false)) {
            dd(2);
            // Facade kullanımı
            IndexAnalyzer::startCapturing();
            // Log dosyasına bilgi ekle
            Log::info('IndexAnalyzer: Sorgu yakalama başlatıldı');
        }

        return $next($request);
    }
}
