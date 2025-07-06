<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Oturumda dil tercihi varsa kullan
        if (Session::has('locale')) {
            $locale = Session::get('locale');
            App::setLocale($locale);
        }

        return $next($request);
    }
}
