<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    /**
     * Desteklenen dilleri listele
     *
     * @return JsonResponse
     */
    public function getSupportedLanguages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'locales' => config('language.locales', ['en' => 'English', 'tr' => 'Türkçe']),
            'current' => App::getLocale()
        ]);
    }

    /**
     * Dili değiştir
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function changeLanguage(Request $request): JsonResponse
    {
        $locale = $request->input('locale');

        // Desteklenen bir dil mi kontrol et
        $supportedLocales = array_keys(config('language.locales', ['en' => 'English', 'tr' => 'Türkçe']));

        if (!in_array($locale, $supportedLocales)) {
            $locale = config('language.default_locale', 'en');
        }

        // Oturumda dil tercihini sakla
        Session::put('locale', $locale);

        // Dili ayarla
        App::setLocale($locale);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'message' => 'Language changed to ' . config('language.locales.' . $locale, $locale)
        ]);
    }

    /**
     * Uygulama dilini değiştirir
     *
     * @param Request $request
     * @param string $locale
     * @return JsonResponse
     */
    public function setLocale(Request $request, string $locale): JsonResponse
    {
        // Desteklenen diller içinde mi kontrol et
        $locales = Config::get('language.locales', ['en' => 'English', 'tr' => 'Türkçe']);

        if (!array_key_exists($locale, $locales)) {
            return response()->json([
                'success' => false,
                'message' => 'Desteklenmeyen dil kodu: ' . $locale
            ]);
        }

        // Dili oturuma kaydet
        Session::put('locale', $locale);

        // Geçerli istekte dili değiştir
        App::setLocale($locale);

        return response()->json([
            'success' => true,
            'message' => 'Dil başarıyla değiştirildi: ' . $locales[$locale]
        ]);
    }
}
