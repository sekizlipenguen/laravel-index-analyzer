<?php

namespace SekizliPenguen\IndexAnalyzer\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InjectDebugBarMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
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
     * @param Request $request
     * @param mixed $response
     * @return bool
     */
    protected function shouldInject($request, $response)
    {
        if (!config('index-analyzer.enabled', false)) {
            return false;
        }

        // IndexAnalyzer tarayıcısından gelen isteklere izin ver
        if ($request->header('X-IndexAnalyzer-Crawler') === 'true') {
            return false;  // Zaten AJAX isteği olarak işlenecek
        }

        if ($request->ajax() || $request->wantsJson() || $request->isJson()) {
            return false;
        }

        // JsonResponse kontrolü
        if ($response instanceof JsonResponse) {
            return false;
        }

        // Yönlendirme (RedirectResponse) kontrolü
        if (method_exists($response, 'isRedirection') && $response->isRedirection()) {
            return false;
        }

        // Sadece HTML içerik tipine sahip yanıtlara enjekte et
        if ($response instanceof Response) {
            if (!$response->headers->has('Content-Type') ||
                strpos($response->headers->get('Content-Type'), 'text/html') === false) {
                return false;
            }
        }

        // X-Requested-With header'ı ile yapılan Ajax istekleri için tarayıcı kontrolü
        if ($request->header('X-Requested-With') === 'XMLHttpRequest' &&
            $request->header('X-IndexAnalyzer-Crawler') !== 'true') {
            return false;
        }

        // Content olup olmadığını kontrol et
        if (!method_exists($response, 'getContent')) {
            return false;
        }

        return true;
    }

    /**
     * Inject the debug bar into the response.
     *
     * @param mixed $response
     * @return void
     */
    protected function injectDebugBar($response): void
    {
        // Eğer response bir Response sınıfı değilse işlem yapma
        if (!method_exists($response, 'getContent') || !method_exists($response, 'setContent')) {
            return;
        }

        $content = $response->getContent();

        if (!is_string($content)) {
            return;
        }

        $debugBarJs = view('index-analyzer::debugbar', [
            'settings' => [
                'position' => config('index-analyzer.debug_bar.position', 'bottom'),
                'theme' => config('index-analyzer.debug_bar.theme', 'light'),
                'autoShow' => config('index-analyzer.debug_bar.auto_show', true),
                'routePrefix' => config('index-analyzer.route_prefix', 'index-analyzer'),
            ],
        ])->render();

        // Debug bar'ın zaten enjekte edilip edilmediğini kontrol et
        if (strpos($content, 'id="ia-debug-bar"') !== false) {
            return;
        }

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
