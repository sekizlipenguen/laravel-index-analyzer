<?php

namespace SekizliPenguen\LaravelIndexAnalyzer\Http\Middleware;

use Closure;
use SekizliPenguen\LaravelIndexAnalyzer\Facades\IndexAnalyzer;

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
            IndexAnalyzer::startCapturing();
        }

        return $next($request);
    }
}
