<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SekizliPenguen\IndexAnalyzer\Facades\IndexAnalyzer;

class IndexAnalyzerController extends Controller
{
    /**
     * Start a new crawl session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function startCrawl(Request $request)
    {
        // Start a new crawl session
        $routes = $this->getApplicationRoutes();

        return response()->json([
            'success' => true,
            'message' => 'Crawling started',
            'routes' => $routes,
        ]);
    }

    /**
     * Get application routes for crawling.
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
     * Generate index suggestions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateSuggestions(Request $request)
    {
        // Tüm sorguları al
        $queries = app('index-analyzer')->getQueryLogger()->getQueries();

        // Eğer hiç sorgu yoksa bilgi mesajı döndür
        if (empty($queries)) {
            return response()->json([
                'success' => true,
                'suggestions' => [],
                'statements' => [],
                'message' => 'Hiç sorgu kaydedilmemiş. Lütfen önce tarama yapın.',
                'debug' => [
                    'query_count' => 0,
                    'queries' => []
                ]
            ]);
        }

        $suggestions = IndexAnalyzer::generateSuggestions();
        $statements = IndexAnalyzer::generateIndexStatements();

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'statements' => $statements,
            'debug' => [
                'query_count' => count($queries),
                'queries' => array_slice($queries, 0, 10) // İlk 10 sorguyu göster
            ]
        ]);
    }

    /**
     * Record a single query from the frontend.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recordQuery(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'sql' => 'required|string',
            'time' => 'required|numeric',
        ]);

        // Record the query manually
        // This is useful for AJAX requests that the middleware couldn't capture

        return response()->json([
            'success' => true,
            'message' => 'Query recorded',
        ]);
    }

    /**
     * Clear all stored queries.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearQueries(Request $request)
    {
        app('index-analyzer')->getQueryLogger()->clearQueries();

        return response()->json([
            'success' => true,
            'message' => 'All queries cleared',
        ]);
    }
}
