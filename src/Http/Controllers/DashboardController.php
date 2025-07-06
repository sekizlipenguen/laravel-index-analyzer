<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Controllers;

use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    /**
     * Display the index analyzer dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $queries = app('index-analyzer')->getQueryLogger()->getQueries();
        $queryCount = count($queries);

        return view('index-analyzer::dashboard', [
            'queryCount' => $queryCount,
            'recentQueries' => array_slice($queries, 0, 10),
            'routePrefix' => config('index-analyzer.route_prefix', 'index-analyzer'),
        ]);
    }
}
