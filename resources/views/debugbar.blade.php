<script>
  (function() {
    // IndexAnalyzer DebugBar
    const debugBarSettings = {!! json_encode($settings) !!};

    function createDebugBar() {
      const debugBarElement = document.createElement('div');
      debugBarElement.id = 'ia-debug-bar';
      debugBarElement.className = `ia-debug-bar ia-position-${debugBarSettings.position} ia-theme-${debugBarSettings.theme}`;

      debugBarElement.innerHTML = `
            <div class="ia-debug-bar-header">
                <div class="ia-logo">{{ __('index-analyzer::index-analyzer.title') }}</div>
                <div class="ia-actions">
                    <button id="ia-start-crawl" class="ia-btn ia-btn-primary">{{ __('index-analyzer::index-analyzer.start_scan') }}</button>
                    <button id="ia-generate-indexes" class="ia-btn ia-btn-success">{{ __('index-analyzer::index-analyzer.extract_indexes') }}</button>
                    <button id="ia-refresh-queries" class="ia-btn ia-btn-info">{{ __('index-analyzer::index-analyzer.refresh_queries') }}</button>
                    <button id="ia-clear-queries" class="ia-btn ia-btn-danger">{{ __('index-analyzer::index-analyzer.clear_all') }}</button>
                    <button id="ia-toggle" class="ia-btn ia-btn-secondary">{{ __('index-analyzer::index-analyzer.hide') }}</button>
                </div>
            </div>
            <div class="ia-debug-bar-content">
                <div id="ia-status" class="ia-status">{{ __('index-analyzer::index-analyzer.ready') }}</div>
                <div id="ia-progress" class="ia-progress">
                    <div id="ia-progress-bar" class="ia-progress-bar" style="width: 0%;"></div>
                </div>
                <div id="ia-results" class="ia-results"></div>
            </div>
        `;

      document.body.appendChild(debugBarElement);

      return debugBarElement;
    }

    function setupStyles() {
      const style = document.createElement('style');
      style.textContent = `
            .ia-debug-bar {
                position: fixed;
                left: 0;
                right: 0;
                z-index: 99999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 14px;
                line-height: 1.5;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
                transition: transform 0.3s ease;
            }

            .ia-debug-bar.ia-position-bottom {
                bottom: 0;
                border-top: 1px solid #ddd;
            }

            .ia-debug-bar.ia-position-top {
                top: 0;
                border-bottom: 1px solid #ddd;
            }

            .ia-debug-bar.ia-hidden.ia-position-bottom {
                transform: translateY(100%);
            }

            .ia-debug-bar.ia-hidden.ia-position-top {
                transform: translateY(-100%);
            }

            .ia-debug-bar.ia-theme-light {
                background: #fff;
                color: #333;
            }

            .ia-debug-bar.ia-theme-dark {
                background: #333;
                color: #fff;
            }

            .ia-debug-bar-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            }

            .ia-theme-dark .ia-debug-bar-header {
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .ia-logo {
                font-weight: bold;
                font-size: 16px;
            }

            .ia-actions {
                display: flex;
                gap: 10px;
            }

            .ia-btn {
                padding: 6px 12px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.2s ease;
            }

            .ia-btn-primary {
                background: #4a6cf7;
                color: white;
            }

            .ia-btn-primary:hover {
                background: #3a5ce5;
            }

            .ia-btn-success {
                background: #28a745;
                color: white;
            }

            .ia-btn-success:hover {
                background: #218838;
            }

            .ia-btn-danger {
                background: #dc3545;
                color: white;
            }

            .ia-btn-danger:hover {
                background: #c82333;
            }

            .ia-btn-info {
                background: #17a2b8;
                color: white;
            }

            .ia-btn-info:hover {
                background: #138496;
            }

            .ia-btn-secondary {
                background: #6c757d;
                color: white;
            }

            .ia-btn-secondary:hover {
                background: #5a6268;
            }

            .ia-debug-bar-content {
                padding: 15px;
                max-height: 300px;
                overflow-y: auto;
            }

            .ia-status {
                margin-bottom: 10px;
                font-weight: bold;
            }

            .ia-progress {
                height: 10px;
                background: #f5f5f5;
                border-radius: 5px;
                margin-bottom: 15px;
                overflow: hidden;
            }

            .ia-theme-dark .ia-progress {
                background: #555;
            }

            .ia-progress-bar {
                height: 100%;
                background: #4a6cf7;
                width: 0%;
                transition: width 0.3s ease;
            }

            .ia-results {
                white-space: pre-wrap;
                font-family: monospace;
                background: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
                display: none;
            }

            .ia-theme-dark .ia-results {
                background: #444;
            }

            .ia-copy-btn {
                background: #4a6cf7;
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 10px;
            }

            .ia-copy-btn:hover {
                background: #3a5ce5;
            }
        `;

      document.head.appendChild(style);
    }

    function setupEventListeners(debugBar) {
      const startCrawlBtn = document.getElementById('ia-start-crawl');
      const generateIndexesBtn = document.getElementById('ia-generate-indexes');
      const clearQueriesBtn = document.getElementById('ia-clear-queries');
      const toggleBtn = document.getElementById('ia-toggle');
      const statusElement = document.getElementById('ia-status');
      const progressBar = document.getElementById('ia-progress-bar');
      const resultsElement = document.getElementById('ia-results');

      // Toggle debug bar visibility
      toggleBtn.addEventListener('click', () => {
        debugBar.classList.toggle('ia-hidden');
        toggleBtn.textContent = debugBar.classList.contains('ia-hidden') ? '{{ __('index-analyzer::index-analyzer.show') }}' : '{{ __('index-analyzer::index-analyzer.hide') }}';
      });

      // Start crawling
      startCrawlBtn.addEventListener('click', async () => {
        try {
          startCrawlBtn.disabled = true;
          statusElement.textContent = '{{ __('index-analyzer::index-analyzer.scan_starting') }}';
          resultsElement.style.display = 'none';

          const response = await fetch(`/${debugBarSettings.routePrefix}/start-crawl`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': getCSRFToken(),
            },
          });

          const data = await response.json();

          if (data.success) {
            statusElement.textContent = '{{ __('index-analyzer::index-analyzer.scan_started') }}';

            // Start crawling routes
            await crawlRoutes(data.routes, progressBar, statusElement);

            // Son kez istatistikleri güncelle
            await refreshStats();

            statusElement.textContent = '{{ __('index-analyzer::index-analyzer.scan_completed') }}';
          } else {
            statusElement.textContent = '{{ __('index-analyzer::index-analyzer.error') }}: ' + (data.message || '{{ __('index-analyzer::index-analyzer.unknown_error') }}');
          }
        } catch (error) {
          statusElement.textContent = '{{ __('index-analyzer::index-analyzer.error') }}: ' + error.message;
          console.error('Crawl error:', error);
        } finally {
          startCrawlBtn.disabled = false;
          progressBar.style.width = '100%';
        }
      });

      // Generate indexes
      generateIndexesBtn.addEventListener('click', async () => {
        try {
          generateIndexesBtn.disabled = true;
          statusElement.textContent = '{{ __('index-analyzer::index-analyzer.generating_index_suggestions') }}';

          const response = await fetch(`/${debugBarSettings.routePrefix}/generate-suggestions`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': getCSRFToken(),
            },
          });

          const data = await response.json();

          if (data.success) {
            statusElement.textContent = data.message || '{{ __('index-analyzer::index-analyzer.generating_suggestions') }}';

            if (data.statements.length > 0) {
              const statementsText = data.statements.join('\n');
              resultsElement.textContent = statementsText;
              resultsElement.style.display = 'block';

              // Add copy button
              const copyBtn = document.createElement('button');
              copyBtn.className = 'ia-copy-btn';
              copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copy_statements') }}';
              copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(statementsText).then(() => {
                  copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copied') }}';
                  setTimeout(() => {
                    copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copy_statements') }}';
                  }, 2000);
                });
              });
              resultsElement.appendChild(document.createElement('br'));
              resultsElement.appendChild(copyBtn);

              // Debug bilgisi ekle
              if (data.debug && data.debug.query_count) {
                const debugInfo = document.createElement('div');
                debugInfo.className = 'ia-debug-info';
                debugInfo.innerHTML = `<br><small>{{ __('index-analyzer::index-analyzer.query_count') }}: ${data.debug.query_count}</small>`;
                resultsElement.appendChild(debugInfo);
              }
            } else {
              resultsElement.textContent = '{{ __('index-analyzer::index-analyzer.no_suggestions') }}' + (data.message ? ' ' + data.message : '');
              resultsElement.style.display = 'block';

              // Debug bilgisi ekle
              if (data.debug) {
                const debugInfo = document.createElement('div');
                debugInfo.className = 'ia-debug-info';
                debugInfo.innerHTML = `<br><small>{{ __('index-analyzer::index-analyzer.query_count') }}: ${data.debug.query_count || 0}</small>`;
                resultsElement.appendChild(debugInfo);
              }
            }
          } else {
            statusElement.textContent = '{{ __('index-analyzer::index-analyzer.error') }}: ' + (data.message || '{{ __('index-analyzer::index-analyzer.unknown_error') }}');
          }
        } catch (error) {
          statusElement.textContent = '{{ __('index-analyzer::index-analyzer.error') }}: ' + error.message;
          console.error('Generate indexes error:', error);
        } finally {
          generateIndexesBtn.disabled = false;
        }
      });

      // Clear queries
      clearQueriesBtn.addEventListener('click', async () => {
        try {
          clearQueriesBtn.disabled = true;
          statusElement.textContent = '{{ __('index-analyzer::index-analyzer.clear_queries') }}...';
          resultsElement.style.display = 'none';

          const response = await fetch(`/${debugBarSettings.routePrefix}/clear-queries`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': getCSRFToken(),
            },
          });

          const data = await response.json();

          if (data.success) {
            statusElement.textContent = '{{ __('index-analyzer::index-analyzer.queries_cleared') }}';
            progressBar.style.width = '0%';

            // Temizlik sonrası istatistikleri sıfırla
            const resultsElement = document.getElementById('ia-results');
            const existingDebugInfo = resultsElement.querySelector('.ia-debug-info');
            if (existingDebugInfo) {
              existingDebugInfo.innerHTML = '<br><small>{{ __('index-analyzer::index-analyzer.query_count') }}: 0</small>';
            }
          } else {
            statusElement.textContent = '{{ __('index-analyzer::index-analyzer.error') }}: ' + (data.message || '{{ __('index-analyzer::index-analyzer.unknown_error') }}');
          }
        } catch (error) {
          statusElement.textContent = '{{ __('index-analyzer::index-analyzer.error') }}: ' + error.message;
          console.error('Clear queries error:', error);
        } finally {
          clearQueriesBtn.disabled = false;
        }
      });
    }

    async function crawlRoutes(routes, progressBar, statusElement) {
      const totalRoutes = routes.length;
      let completed = 0;
      const concurrentRequests = 1; // Aynı anda kaç istek yapılacak
      let activeRequests = 0;
      let index = 0;

      // CSRF token alma
      const csrfToken = getCSRFToken();

      return new Promise((resolve) => {
        function updateProgress() {
          const percentage = (completed / totalRoutes) * 100;
          progressBar.style.width = percentage + '%';
          statusElement.textContent = `{{ __('index-analyzer::index-analyzer.scanning') }}: ${completed}/${totalRoutes} {{ __('index-analyzer::index-analyzer.page') }} (${Math.round(percentage)}%)`;
        }

        async function processRoute() {
          if (index >= routes.length) {
            if (activeRequests === 0) {
              resolve();
            }
            return;
          }

          const route = routes[index++];
          activeRequests++;

          try {
            // Yeni bir yaklaşım dene - önce sayfayı GET ile ziyaret et, sonra iframede aç
            await visitPageAndLoadInIframe(route);

            // Her sayfadan sonra 1 saniye bekle - bu, istek yoğunluğunu azaltır
            await new Promise(r => setTimeout(r, 1000));
          } catch (error) {
            console.error(`Error crawling ${route}:`, error);
          }

          completed++;
          activeRequests--;
          updateProgress();

          // Start next route
          processRoute();
        }

        // Sayfayı hem doğrudan ziyaret et hem de iframe içinde yükle
        async function visitPageAndLoadInIframe(route) {
          // İlk olarak doğrudan bir fetch isteği gönder - bu sorguları kaydedecek
          try {
            const fetchOptions = {
              method: 'GET',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
                'X-CSRF-TOKEN': csrfToken,
              },
              credentials: 'same-origin',
            };

            // Fetch çağrısı
            await fetch(route, fetchOptions).catch(e => console.log('Fetch request for', route, 'failed:', e));
          } catch (fetchError) {
            console.log('{{ __('index-analyzer::index-analyzer.fetch_error') }}:', fetchError);
            // Fetch hatası olsa bile devam et
          }

          // Şimdi iframe içinde yükle
          return new Promise((resolveIframeLoad) => {
            // Create a hidden iframe to load the page
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.left = '-9999px';
            iframe.style.width = '500px'; // Daha geniş - JavaScript'in çalışması için
            iframe.style.height = '500px';
            document.body.appendChild(iframe);

            let iframeLoaded = false;

            // Iframe yükleme olayını dinle
            iframe.addEventListener('load', () => {
              iframeLoaded = true;

              // Iframe içindeki JavaScript'in çalışması için biraz zaman ver
              setTimeout(() => {
                try {
                  document.body.removeChild(iframe);
                } catch (e) {
                  // İframe kaldırılırken hata oluşursa yoksay
                }
                resolveIframeLoad();
              }, 3000); // JavaScript'in çalışması için 3 saniye bekle
            });

            // Hata durumunda da devam et
            iframe.addEventListener('error', () => {
              try {
                document.body.removeChild(iframe);
              } catch (e) {
                // İframe kaldırılırken hata oluşursa yoksay
              }
              resolveIframeLoad();
            });

            // Zaman aşımı güvenliği
            setTimeout(() => {
              if (!iframeLoaded) {
                try {
                  document.body.removeChild(iframe);
                } catch (e) {
                  // İframe kaldırılırken hata oluşursa yoksay
                }
                resolveIframeLoad();
              }
            }, 15000); // 15 saniye maksimum bekleme

            // Iframe'e sayfayı yükle
            iframe.src = route;
          });
        }

        updateProgress();

        // Start initial batch of requests
        for (let i = 0; i < concurrentRequests && i < routes.length; i++) {
          processRoute();
        }
      });
    }

    function getCSRFToken() {
      // Try to get CSRF token from meta tag
      const metaToken = document.querySelector('meta[name="csrf-token"]');
      if (metaToken) {
        return metaToken.getAttribute('content');
      }

      // Try to get from form
      const tokenInput = document.querySelector('input[name="_token"]');
      if (tokenInput) {
        return tokenInput.value;
      }

      return '';
    }

    // Initialize the debug bar
    function init() {
      if (document.getElementById('ia-debug-bar')) {
        return; // Already initialized
      }

      setupStyles();
      const debugBar = createDebugBar();
      setupEventListeners(debugBar);

      // Auto-hide if configured
      if (!debugBarSettings.autoShow) {
        debugBar.classList.add('ia-hidden');
        document.getElementById('ia-toggle').textContent = '{{ __('index-analyzer::index-analyzer.show') }}';
      }
    }

    // Initialize after DOM is loaded
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    } else {
      init();
    }
  })();
</script>
