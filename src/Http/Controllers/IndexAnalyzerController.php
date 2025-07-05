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

        foreach (app('router')->getRoutes() as $route) {
            $methods = $route->methods();

            // Only GET routes are crawlable
            if (!in_array('GET', $methods)) {
                continue;
            }

            $uri = $route->uri();

            // Skip routes that aren't web pages
            if (strpos($uri, 'api/') === 0 ||
                strpos($uri, '_debugbar') === 0 ||
                strpos($uri, '_ignition') === 0 ||
                strpos($uri, config('index-analyzer.route_prefix', 'index-analyzer')) === 0) {
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
        $suggestions = IndexAnalyzer::generateSuggestions();
        $statements = IndexAnalyzer::generateIndexStatements();

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'statements' => $statements,
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
