<?php

namespace SekizliPenguen\LaravelIndexAnalyzer\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InjectDebugBarMiddleware
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
        $response = $next($request);

        if (!$this->shouldInject($request, $response)) {
            return $response;
        }

        $this->injectDebugBar($response);

        return $response;
    }

    /**
     * Determine if we should inject the debug bar.
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $response
     * @return bool
     */
    protected function shouldInject($request, $response)
    {
        if (!config('index-analyzer.enabled', false)) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson() || $request->isJson()) {
            return false;
        }

        if ($response instanceof JsonResponse) {
            return false;
        }

        if ($response instanceof Response &&
            !($response->headers->has('Content-Type') &&
                strpos($response->headers->get('Content-Type'), 'text/html') !== false)) {
            return false;
        }

        return true;
    }

    /**
     * Inject the debug bar into the response.
     *
     * @param \Illuminate\Http\Response $response
     * @return void
     */
    protected function injectDebugBar($response)
    {
        $content = $response->getContent();

        if (!is_string($content)) {
            return;
        }

        $debugBarJs = view('laravel-index-analyzer::debugbar', [
            'settings' => [
                'position' => config('index-analyzer.debug_bar.position', 'bottom'),
                'theme' => config('index-analyzer.debug_bar.theme', 'light'),
                'autoShow' => config('index-analyzer.debug_bar.auto_show', true),
                'routePrefix' => config('index-analyzer.route_prefix', 'index-analyzer'),
            ],
        ])->render();

        // Inject before the </body> tag
        $bodyPosition = strripos($content, '</body>');

        if ($bodyPosition !== false) {
            $content = substr($content, 0, $bodyPosition) . $debugBarJs . substr($content, $bodyPosition);
        } else {
            $content .= $debugBarJs;
        }

        $response->setContent($content);
    }
}
