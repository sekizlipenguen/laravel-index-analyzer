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
                <div class="ia-logo">Laravel Index Analyzer</div>
                <div class="ia-actions">
                    <button id="ia-start-crawl" class="ia-btn ia-btn-primary">Taramayı Başlat</button>
                    <button id="ia-generate-indexes" class="ia-btn ia-btn-success">İndeksleri Çıkar</button>
                    <button id="ia-clear-queries" class="ia-btn ia-btn-danger">Temizle</button>
                    <button id="ia-toggle" class="ia-btn ia-btn-secondary">Gizle</button>
                </div>
            </div>
            <div class="ia-debug-bar-content">
                <div id="ia-status" class="ia-status">Hazır</div>
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
        toggleBtn.textContent = debugBar.classList.contains('ia-hidden') ? 'Göster' : 'Gizle';
      });

      // Start crawling
      startCrawlBtn.addEventListener('click', async () => {
        try {
          startCrawlBtn.disabled = true;
          statusElement.textContent = 'Tarama başlatılıyor...';
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
            statusElement.textContent = 'Tarama başlatıldı';

            // Start crawling routes
            await crawlRoutes(data.routes, progressBar, statusElement);

            statusElement.textContent = 'Tarama tamamlandı';
          } else {
            statusElement.textContent = 'Hata: ' + (data.message || 'Bilinmeyen hata');
          }
        } catch (error) {
          statusElement.textContent = 'Hata: ' + error.message;
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
          statusElement.textContent = 'İndeks önerileri oluşturuluyor...';

          const response = await fetch(`/${debugBarSettings.routePrefix}/generate-suggestions`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': getCSRFToken(),
            },
          });

          const data = await response.json();

          if (data.success) {
            statusElement.textContent = 'İndeks önerileri oluşturuldu';

            if (data.statements.length > 0) {
              const statementsText = data.statements.join('\n');
              resultsElement.textContent = statementsText;
              resultsElement.style.display = 'block';

              // Add copy button
              const copyBtn = document.createElement('button');
              copyBtn.className = 'ia-copy-btn';
              copyBtn.textContent = 'Kopyala';
              copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(statementsText).then(() => {
                  copyBtn.textContent = 'Kopyalandı!';
                  setTimeout(() => {
                    copyBtn.textContent = 'Kopyala';
                  }, 2000);
                });
              });
              resultsElement.appendChild(document.createElement('br'));
              resultsElement.appendChild(copyBtn);
            } else {
              resultsElement.textContent = 'Önerilen indeks bulunamadı.';
              resultsElement.style.display = 'block';
            }
          } else {
            statusElement.textContent = 'Hata: ' + (data.message || 'Bilinmeyen hata');
          }
        } catch (error) {
          statusElement.textContent = 'Hata: ' + error.message;
          console.error('Generate indexes error:', error);
        } finally {
          generateIndexesBtn.disabled = false;
        }
      });

      // Clear queries
      clearQueriesBtn.addEventListener('click', async () => {
        try {
          clearQueriesBtn.disabled = true;
          statusElement.textContent = 'Sorgular temizleniyor...';
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
            statusElement.textContent = 'Sorgular temizlendi';
            progressBar.style.width = '0%';
          } else {
            statusElement.textContent = 'Hata: ' + (data.message || 'Bilinmeyen hata');
          }
        } catch (error) {
          statusElement.textContent = 'Hata: ' + error.message;
          console.error('Clear queries error:', error);
        } finally {
          clearQueriesBtn.disabled = false;
        }
      });
    }

    async function crawlRoutes(routes, progressBar, statusElement) {
      const totalRoutes = routes.length;
      let completed = 0;
      const concurrentRequests = 3; // Aynı anda kaç istek yapılacak
      let activeRequests = 0;
      let index = 0;

      return new Promise((resolve) => {
        function updateProgress() {
          const percentage = (completed / totalRoutes) * 100;
          progressBar.style.width = percentage + '%';
          statusElement.textContent = `Taranıyor: ${completed}/${totalRoutes} sayfa (${Math.round(percentage)}%)`;
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
            // Create a hidden iframe to load the page
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.left = '-9999px';
            iframe.style.width = '1px';
            iframe.style.height = '1px';
            document.body.appendChild(iframe);

            const loadPromise = new Promise((resolveLoad) => {
              iframe.addEventListener('load', resolveLoad);
              iframe.addEventListener('error', resolveLoad);
            });

            iframe.src = route;

            // Wait for page to load with timeout
            const timeoutPromise = new Promise((resolveTimeout) => {
              setTimeout(resolveTimeout, 10000); // 10 seconds timeout
            });

            await Promise.race([loadPromise, timeoutPromise]);

            // Clean up
            document.body.removeChild(iframe);
          } catch (error) {
            console.error(`Error crawling ${route}:`, error);
          }

          completed++;
          activeRequests--;
          updateProgress();

          // Start next route
          processRoute();
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
        document.getElementById('ia-toggle').textContent = 'Göster';
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
