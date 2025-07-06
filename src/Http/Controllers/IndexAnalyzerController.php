<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use SekizliPenguen\IndexAnalyzer\Facades\IndexAnalyzer;

class IndexAnalyzerController extends Controller
{
    /**
     * Yeni bir tarama oturumu başlat.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function startCrawl(Request $request)
    {
        // Dil seçeneğini al ve uygula
        $locale = $request->get('locale', config('language.default_locale', 'en'));
        App::setLocale($locale);

        // Yeni bir tarama oturumu başlat
        $routes = $this->getApplicationRoutes();

        return response()->json([
            'success' => true,
            'message' => __('index-analyzer::index-analyzer.crawling_started'),
            'routes' => $routes,
            'locale' => $locale
        ]);
    }

    /**
     * Tarama için uygulama rotalarını al.
     *
     * @return array
     */
    protected function getApplicationRoutes()
    {
        $routes = [];

        // Yalnızca index-analyzer rotalarını filtrelemek için kullanılacak önekler
        $skipPrefixes = [
            '_debugbar',
            '_ignition',
            'livewire',
            config('index-analyzer.route_prefix', 'index-analyzer')
        ];

        foreach (app('router')->getRoutes() as $route) {
            $methods = $route->methods();
            $uri = $route->uri();

            // Eğer GET metodu yoksa, route'u atla
            if (!in_array('GET', $methods)) {
                continue;
            }

            // Sadece index-analyzer ile ilgili rotaları atla
            $skip = false;
            foreach ($skipPrefixes as $prefix) {
                if (strpos($uri, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $routes[] = '/' . $uri;
        }

        return $routes;
    }

    /**
     * İndeks önerileri oluştur.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateSuggestions(Request $request)
    {
        // Dil seçeneğini al ve uygula
        $locale = $request->get('locale', config('language.default_locale', 'en'));
        App::setLocale($locale);

        // Tüm sorguları al
        $queries = app('index-analyzer')->getQueryLogger()->getQueries();

        // Eğer hiç sorgu yoksa bilgi mesajı döndür
        if (empty($queries)) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
                'statements' => [],
                'message' => __('index-analyzer::index-analyzer.no_queries_recorded'),
                'debug' => [
                    'query_count' => 0,
                    'queries' => []
                ],
                'locale' => $locale
            ]);
        }

        $suggestions = IndexAnalyzer::generateSuggestions();
        $statements = IndexAnalyzer::generateIndexStatements();

        // Mevcut indeksleri al
        $existingSuggestions = IndexAnalyzer::generateExistingSuggestions();
        $existingStatements = IndexAnalyzer::generateExistingIndexStatements();

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'statements' => $statements,
            'existingSuggestions' => $existingSuggestions,
            'existingIndexes' => $existingStatements,
            'newIndexes' => $statements,
            'debug' => [
                'query_count' => count($queries),
                'queries' => array_slice($queries, 0, 10) // İlk 10 sorguyu göster
            ],
            'locale' => $locale,
            'translations' => [
                'table' => __('index-analyzer::index-analyzer.table'),
                'columns' => __('index-analyzer::index-analyzer.columns'),
                'index_name' => __('index-analyzer::index-analyzer.index_name'),
                'statements' => __('index-analyzer::index-analyzer.statements'),
                'apply_to_database' => __('index-analyzer::index-analyzer.apply_to_database'),
                'debug_info' => __('index-analyzer::index-analyzer.debug_info'),
                'query_count' => __('index-analyzer::index-analyzer.query_count'),
                'sample_queries' => __('index-analyzer::index-analyzer.sample_queries'),
                'existing_indexes' => __('index-analyzer::index-analyzer.existing_indexes'),
                'new_indexes' => __('index-analyzer::index-analyzer.new_indexes'),
            ]
        ]);
    }

    /**
     * Ön yüzden tek bir sorguyu kaydet.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recordQuery(Request $request)
    {
        // Dil seçeneğini al ve uygula
        $locale = $request->get('locale', config('language.default_locale', 'en'));
        App::setLocale($locale);

        $request->validate([
            'url' => 'required|string',
            'sql' => 'required|string',
            'time' => 'required|numeric',
        ]);

        // Sorguyu manuel olarak kaydet
        // Bu, ara yazılımın yakalayamadığı AJAX istekleri için kullanışlıdır

        return response()->json([
            'success' => true,
            'message' => __('index-analyzer::index-analyzer.query_recorded'),
            'locale' => $locale
        ]);
    }

    /**
     * Tüm depolanan sorguları temizle.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearQueries(Request $request)
    {
        // Dil seçeneğini al ve uygula
        $locale = $request->get('locale', config('language.default_locale', 'en'));
        App::setLocale($locale);

        app('index-analyzer')->getQueryLogger()->clearQueries();

        return response()->json([
            'success' => true,
            'message' => __('index-analyzer::index-analyzer.queries_cleared'),
            'locale' => $locale
        ]);
    }

    /**
     * Güncel istatistikleri getir.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(Request $request)
    {
        // Tüm sorguları al
        $queries = app('index-analyzer')->getQueryLogger()->getQueries();

        // İndeks sayılarını al
        $existingSuggestions = [];
        $newSuggestions = [];

        // Sorgular varsa indeks analizini yap
        if (!empty($queries)) {
            try {
                $existingSuggestions = IndexAnalyzer::generateExistingSuggestions();
                $newSuggestions = IndexAnalyzer::generateSuggestions();
            } catch (\Exception $e) {
                // Hata durumunda boş arrays ile devam et
                // Bu genellikle henüz indekslenebilir sorgular oluşmadığında oluşabilir
            }
        }

        return response()->json([
            'success' => true,
            'queryCount' => count($queries),
            'existingIndexCount' => count($existingSuggestions),
            'newIndexCount' => count($newSuggestions)
        ]);
    }
}
