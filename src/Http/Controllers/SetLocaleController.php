<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SetLocaleController extends Controller
{
    /**
     * Dil değiştirme işlemini yapar (URL parametresiyle)
     *
     * @param Request $request
     * @param string $locale
     * @return JsonResponse
     */
    public function setLocale(Request $request, string $locale): JsonResponse
    {
        // Debug bilgilerini kaydet
        $debugInfo = [
            'isAjax' => $request->ajax(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
            'method' => $request->method(),
            'path' => $request->path(),
            'url' => $request->url(),
            'ip' => $request->ip(),
            'locale_param' => $locale
        ];

        Log::info('setLocale çağrıldı', $debugInfo);

        // Desteklenen diller içinde mi kontrol et
        $locales = Config::get('language.locales', ['en' => 'English', 'tr' => 'Türkçe']);

        if (!array_key_exists($locale, $locales)) {
            Log::warning('Desteklenmeyen dil kodu: ' . $locale);
            return response()->json([
                'success' => false,
                'message' => 'Desteklenmeyen dil kodu: ' . $locale,
                'debug' => $debugInfo
            ]);
        }

        // Dili oturuma kaydet
        Session::put('locale', $locale);
        Log::info('Oturuma dil kaydedildi: ' . $locale);

        // Geçerli istekte dili değiştir
        App::setLocale($locale);
        Log::info('Uygulama dili değiştirildi: ' . $locale);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'debug' => $debugInfo,
            'message' => 'Dil başarıyla değiştirildi: ' . $locales[$locale]
        ]);
    }
}
