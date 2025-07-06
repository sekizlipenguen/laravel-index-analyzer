<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
        // Mevcut dili kaydedelim
        $oldLocale = App::getLocale();

        // Oturumda dil tercihi varsa kullan
        if (Session::has('locale')) {
            $locale = Session::get('locale');

            // Geçerli bir locale değeri mi kontrol et
            $locales = Config::get('language.locales', ['en' => 'English', 'tr' => 'Türkçe']);
            if (array_key_exists($locale, $locales)) {
                App::setLocale($locale);
                Log::debug('LocaleMiddleware: Dil değiştirildi', [
                    'old_locale' => $oldLocale,
                    'new_locale' => $locale,
                    'url' => $request->fullUrl(),
                    'session_id' => Session::getId(),
                ]);
            } else {
                Log::warning('LocaleMiddleware: Geçersiz dil kodu', [
                    'locale' => $locale
                ]);
            }
        } else {
            Log::debug('LocaleMiddleware: Oturumda dil tercihi yok');
        }

        return $next($request);
    }
}
