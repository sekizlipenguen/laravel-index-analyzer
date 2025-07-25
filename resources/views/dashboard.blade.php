<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Laravel Index Analyzer - Kontrol Paneli</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        h1 {
            margin: 0;
            color: #4a6cf7;
        }

        .dashboard-card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card-title {
            margin-top: 0;
            margin-bottom: 10px !important;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            color: #4a6cf7;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #4a6cf7;
        }

        .stat-label {
            color: #6c757d;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background: #4a6cf7;
            color: white;
        }

        .btn-primary:hover {
            background: #3a5ce5;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .routes-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }

        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }

        #results {
            white-space: pre-wrap;
            font-family: monospace;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .language-selector {
            align-self: flex-start;
        }

        .index-sections .alert {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }

        .index-sections .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        .index-sections .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .index-sections h4 {
            margin-top: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: #495057;
        }
    </style>
    <!-- Bootstrap CSS ve JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Font Awesome ikonları -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <header>
        <div class="header-content">
            <div>
                <h1>{{ __('index-analyzer::index-analyzer.title') }}</h1>
                <p>{{ __('index-analyzer::index-analyzer.description') }}</p>
            </div>
            <div class="language-selector">
                <div class="language-selector dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa fa-globe"></i> {{ __('index-analyzer::index-analyzer.language') }}: {{ config('language.locales.' . App::getLocale()) }}
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                        @foreach(config('language.locales', ['en' => 'English', 'tr' => 'Türkçe']) as $code => $name)
                            <li>
                                <a class="dropdown-item {{ App::getLocale() == $code ? 'active' : '' }}" href="javascript:void(0);" data-language-code="{{ $code }}">
                                    {{ $name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="actions">
        <button id="startCrawl" class="btn btn-primary">{{ __('index-analyzer::index-analyzer.start_scan') }}</button>
        <button id="generateIndexes" class="btn btn-success">{{ __('index-analyzer::index-analyzer.extract_indexes') }}</button>
        <button id="clearQueries" class="btn btn-danger">{{ __('index-analyzer::index-analyzer.clear_queries') }}</button>
    </div>

    <div class="dashboard-card">
        <h2 class="card-title">{{ __('index-analyzer::index-analyzer.dashboard') }}</h2>
        <div class="stats">
            <div class="stat-card">
                <div id="query-count" class="stat-value">{{ $queryCount }}</div>
                <div class="stat-label">{{ __('index-analyzer::index-analyzer.total_queries') }}</div>
            </div>
            <div class="stat-card">
                <div id="existing-index-count" class="stat-value">0</div>
                <div class="stat-label">{{ __('index-analyzer::index-analyzer.total_existing_indexes') }}</div>
            </div>
            <div class="stat-card">
                <div id="new-index-count" class="stat-value">0</div>
                <div class="stat-label">{{ __('index-analyzer::index-analyzer.total_new_indexes') }}</div>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h2 class="card-title">{{ __('index-analyzer::index-analyzer.sample_queries') }}</h2>
        <table id="sample-queries-table">
            <thead>
            <tr>
                <th>SQL</th>
                <th>{{ __('index-analyzer::index-analyzer.time_ms') }}</th>
                <th>{{ __('index-analyzer::index-analyzer.date') }}</th>
            </tr>
            </thead>
            <tbody>
            @if(count($recentQueries) > 0)
                @foreach($recentQueries as $query)
                    <tr>
                        <td>{{ Str::limit($query['sql'] ?? 'N/A', 100) }}</td>
                        <td>{{ $query['time'] ?? 'N/A' }}</td>
                        <td>{{ $query['date'] ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="3">{{ __('index-analyzer::index-analyzer.no_queries_recorded') }}</td>
                </tr>
            @endif
            </tbody>
        </table>
        <div class="mt-3">
            <button id="refreshQueriesBtn" class="btn btn-sm btn-secondary">
                <i class="fa fa-refresh"></i>
                {{ __('index-analyzer::index-analyzer.refresh_queries') }}
            </button>
        </div>
    </div>

    <div class="dashboard-card">
        <h2 class="card-title">{{ __('index-analyzer::index-analyzer.suggestions') }}</h2>
        <p>{{ __('index-analyzer::index-analyzer.suggestions_hint') }}</p>
        <div class="index-sections">
            <div class="existing-indexes-section mb-4" style="display: none;">
                <h4>
                    <i class="fa fa-check-circle text-success me-2"></i>{{ __('index-analyzer::index-analyzer.existing_indexes') }}</h4>
                <p>{{ __('index-analyzer::index-analyzer.existing_indexes_desc') }}</p>
                <div id="existing-indexes"></div>
            </div>

            <div class="new-indexes-section" style="display: none;">
                <h4>
                    <i class="fa fa-plus-circle text-primary me-2"></i>{{ __('index-analyzer::index-analyzer.new_indexes') }}</h4>
                <p>{{ __('index-analyzer::index-analyzer.new_indexes_desc') }}</p>
                <div id="new-indexes"></div>
            </div>

            <div id="results"></div>
        </div>
    </div>
</div>

<!-- Tarama Modalı -->
<div id="routesModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>{{ __('index-analyzer::index-analyzer.routes_to_scan') }}</h2>
        <p>{{ __('index-analyzer::index-analyzer.routes_to_scan_desc') }}</p>
        <div class="routes-list" id="routesList"></div>
        <div id="crawlProgress">
            <p id="progressText">{{ __('index-analyzer::index-analyzer.scan_starting') }}</p>
            <progress id="progressBar" value="0" max="100" style="width: 100%"></progress>
        </div>
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Otomatik yenileme için değişkenler
    let autoRefreshStats = false;
    let refreshInterval = null;
    const REFRESH_RATE = 5000; // 5 saniyede bir yenileme

    // Sayfa yüklendiğinde mevcut sorgu sayısını kontrol et ve kaydet
    let initialQueryCount = parseInt(document.getElementById('query-count').textContent) || 0;

    const startCrawlBtn = document.getElementById('startCrawl');
    const generateIndexesBtn = document.getElementById('generateIndexes');
    const clearQueriesBtn = document.getElementById('clearQueries');
    const resultsElement = document.getElementById('results');
    const routesModal = document.getElementById('routesModal');
    const closeModal = document.querySelector('.close');
    const routesList = document.getElementById('routesList');
    const progressText = document.getElementById('progressText');
    const progressBar = document.getElementById('progressBar');
    const routePrefix = '{{ $routePrefix }}';

    // İlk yenilemeyi başlat
    refreshStats();

    // İstatistikleri güncelleyecek fonksiyon
    async function refreshStats() {
      try {
        // Sorgu sayısını al
        const statsResponse = await fetch(`/${routePrefix}/get-stats`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (statsResponse.ok) {
          const statsData = await statsResponse.json();
          if (statsData.success && statsData.queryCount !== undefined) {
            // Sorgu sayısını güncelle
            document.getElementById('query-count').textContent = statsData.queryCount;

            // İndeks sayılarını güncelle (eğer varsa)
            if (statsData.existingIndexCount !== undefined) {
              document.getElementById('existing-index-count').textContent = statsData.existingIndexCount;
            }

            if (statsData.newIndexCount !== undefined) {
              document.getElementById('new-index-count').textContent = statsData.newIndexCount;
            }

            // Tabloda yeni sorguları görmek için sayfayı yenile - ancak düğmeler tıklandığında
            // Yalnızca otomatik tarama aktifse ve sorgu sayısı değiştiyse yenileme yap
            if (autoRefreshStats && parseInt(document.getElementById('query-count').textContent) !== statsData.queryCount) {
              // Sorgu sayısı değişti, 1 saniye sonra sayfayı yenile
              setTimeout(() => {
                location.reload();
              }, 1000);
            }
          }
        }
      } catch (error) {
        console.error('İstatistik yenileme hatası:', error);
      }
    }

    function getCSRFToken() {
      return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    // Otomatik yenilemeyi başlat
    function startAutoRefresh() {
      autoRefreshStats = true;
      if (!refreshInterval) {
        refreshInterval = setInterval(refreshStats, REFRESH_RATE);
        console.log('Otomatik yenileme başlatıldı');
      }
    }

    // Otomatik yenilemeyi durdur
    function stopAutoRefresh() {
      autoRefreshStats = false;
      if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
        console.log('Otomatik yenileme durduruldu');
      }
    }

    startCrawlBtn.addEventListener('click', async function() {
      try {
        startCrawlBtn.disabled = true;
        // Otomatik yenilemeyi başlat
        startAutoRefresh();

        const response = await fetch(`/${routePrefix}/start-crawl`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
          },
        });

        const data = await response.json();

        if (data.success) {
          // Show routes in modal
          routesList.innerHTML = '';
          data.routes.forEach(route => {
            const routeItem = document.createElement('div');
            routeItem.textContent = route;
            routesList.appendChild(routeItem);
          });

          // Show modal
          routesModal.style.display = 'flex';

          // Start crawling
          await crawlRoutes(data.routes);
        } else {
          alert('Hata: ' + (data.message || 'Bilinmeyen hata'));
        }
      } catch (error) {
        alert('Hata: ' + error.message);
        console.error('Crawl error:', error);
      } finally {
        startCrawlBtn.disabled = false;
      }
    });

    generateIndexesBtn.addEventListener('click', async function() {
      try {
        generateIndexesBtn.disabled = true;
        // Otomatik yenilemeyi durdur
        stopAutoRefresh();
        resultsElement.textContent = '{{ __('index-analyzer::index-analyzer.generating_suggestions') }}';

        // İndeks sayılarını sıfırla


        const response = await fetch(`/${routePrefix}/generate-suggestions`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
          },
        });

        const data = await response.json();

        if (data.success) {
          const existingIndexesSection = document.querySelector('.existing-indexes-section');
          const newIndexesSection = document.querySelector('.new-indexes-section');
          const existingIndexesContainer = document.getElementById('existing-indexes');
          const newIndexesContainer = document.getElementById('new-indexes');

          // Sıfırla
          resultsElement.innerHTML = '';
          existingIndexesContainer.innerHTML = '';
          newIndexesContainer.innerHTML = '';
          existingIndexesSection.style.display = 'none';
          newIndexesSection.style.display = 'none';

          // İndeks sayılarını güncelle
          if (data.existingIndexes && Array.isArray(data.existingIndexes)) {
            document.getElementById('existing-index-count').textContent = data.existingIndexes.length;
          }

          if (data.newIndexes && Array.isArray(data.newIndexes)) {
            document.getElementById('new-index-count').textContent = data.newIndexes.length;
          }

          if (data.statements && data.statements.length > 0) {
            // Var olan ve yeni indeksleri al
            const existingIndexes = data.existingIndexes || [];
            const newIndexes = data.newIndexes || [];

            // Var olan indeksler bölümü
            if (existingIndexes.length > 0) {
              existingIndexesSection.style.display = 'block';

              const existingStatementsText = existingIndexes.join('\n');
              existingIndexesContainer.innerHTML = `<pre>${existingStatementsText}</pre>`;

              // Var olan indeksler için toggle butonu
              const toggleExistingBtn = document.createElement('button');
              toggleExistingBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
              toggleExistingBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_existing') }}';
              toggleExistingBtn.addEventListener('click', () => {
                const pre = existingIndexesContainer.querySelector('pre');
                if (pre.style.display === 'none') {
                  pre.style.display = 'block';
                  toggleExistingBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_existing') }}';
                } else {
                  pre.style.display = 'none';
                  toggleExistingBtn.innerHTML = '<i class="fa fa-eye"></i> {{ __('index-analyzer::index-analyzer.toggle_existing') }}';
                }
              });
              existingIndexesContainer.appendChild(toggleExistingBtn);
            } else {
              existingIndexesSection.style.display = 'block';
              existingIndexesContainer.innerHTML = '<div class="alert alert-info">{{ __('index-analyzer::index-analyzer.no_existing_indexes') }}</div>';
            }

            // Yeni indeksler bölümü
            if (newIndexes.length > 0) {
              newIndexesSection.style.display = 'block';

              const newStatementsText = newIndexes.join('\n');
              newIndexesContainer.innerHTML = `<pre>${newStatementsText}</pre>`;

              // Yeni indeksler için butonlar
              const buttonsContainer = document.createElement('div');
              buttonsContainer.className = 'mt-3';

              // Kopyalama butonu
              const copyBtn = document.createElement('button');
              copyBtn.className = 'btn btn-primary me-2';
              copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copy_statements') }}';
              copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(newStatementsText).then(() => {
                  copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copied') }}';
                  setTimeout(() => {
                    copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copy_statements') }}';
                  }, 2000);
                });
              });
              buttonsContainer.appendChild(copyBtn);

              // Toggle butonu
              const toggleNewBtn = document.createElement('button');
              toggleNewBtn.className = 'btn btn-sm btn-outline-secondary';
              toggleNewBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_new') }}';
              toggleNewBtn.addEventListener('click', () => {
                const pre = newIndexesContainer.querySelector('pre');
                if (pre.style.display === 'none') {
                  pre.style.display = 'block';
                  toggleNewBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_new') }}';
                } else {
                  pre.style.display = 'none';
                  toggleNewBtn.innerHTML = '<i class="fa fa-eye"></i> {{ __('index-analyzer::index-analyzer.toggle_new') }}';
                }
              });
              buttonsContainer.appendChild(toggleNewBtn);

              newIndexesContainer.appendChild(buttonsContainer);
            } else {
              newIndexesSection.style.display = 'block';
              newIndexesContainer.innerHTML = '<div class="alert alert-info">{{ __('index-analyzer::index-analyzer.no_new_indexes') }}</div>';
            }

            // Hata ayıklama bilgisi
            if (data.debug && data.debug.query_count) {
              const debugInfo = document.createElement('div');
              debugInfo.innerHTML = `<br><small>{{ __('index-analyzer::index-analyzer.debug_info') }}: ${data.debug.query_count}</small>`;
              resultsElement.appendChild(debugInfo);
            }
          } else {
            resultsElement.innerHTML = '<div class="alert alert-info">{{ __('index-analyzer::index-analyzer.no_suggestions') }}' + (data.message ? ' ' + data.message : '') + '</div>';
          }
        } else {
          resultsElement.innerHTML = '<div class="alert alert-danger">{{ __('index-analyzer::index-analyzer.error') }}: ' + (data.message || '{{ __('index-analyzer::index-analyzer.unknown_error') }}') + '</div>';
        }
      } catch (error) {
        resultsElement.innerHTML = '<div class="alert alert-danger">{{ __('index-analyzer::index-analyzer.error') }}: ' + error.message + '</div>';
        console.error('Generate indexes error:', error);
      } finally {
        generateIndexesBtn.disabled = false;
      }
    });

    clearQueriesBtn.addEventListener('click', async function() {
      if (!confirm('{{ __('index-analyzer::index-analyzer.confirm_clear_queries') }}')) {
        return;
      }

      try {
        clearQueriesBtn.disabled = true;
        // Otomatik yenilemeyi durdur
        stopAutoRefresh();

        const response = await fetch(`/${routePrefix}/clear-queries`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
          },
        });

        const data = await response.json();

        if (data.success) {
          document.getElementById('query-count').textContent = '0';
          alert('{{ __('index-analyzer::index-analyzer.queries_cleared') }}');
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          alert('{{ __('index-analyzer::index-analyzer.error') }}: ' + (data.message || '{{ __('index-analyzer::index-analyzer.unknown_error') }}'));
        }
      } catch (error) {
        alert('{{ __('index-analyzer::index-analyzer.error') }}: ' + error.message);
        console.error('Clear queries error:', error);
      } finally {
        clearQueriesBtn.disabled = false;
      }
    });

    closeModal.addEventListener('click', function() {
      routesModal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
      if (event.target == routesModal) {
        routesModal.style.display = 'none';
      }
      // Manuel olarak örnek sorguları yenileme butonu
      const refreshQueriesBtn = document.getElementById('refreshQueriesBtn');
      if (refreshQueriesBtn) {
        refreshQueriesBtn.addEventListener('click', async function() {
          // Sayfa yenilemeden önce düğmeyi devre dışı bırak
          refreshQueriesBtn.disabled = true;
          refreshQueriesBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Yenileniyor...';

          try {
            // İstatistikleri güncelle
            await refreshStats();

            // Sadece tabloyu yenilemek için sayfayı yenile
            location.reload();
          } catch (error) {
            console.error('Sorgu yenileme hatası:', error);
            refreshQueriesBtn.disabled = false;
            refreshQueriesBtn.innerHTML = '<i class="fa fa-refresh"></i> Sorguları Yenile';
          }
        });
      }
    });

    async function crawlRoutes(routes) {
      const totalRoutes = routes.length;
      let completed = 0;
      progressBar.max = totalRoutes;
      progressBar.value = 0;
      let currentQueryCount = parseInt(document.getElementById('query-count').textContent) || 0;

      // Fetch ve iframe birlikte kullanacağız
      for (const route of routes) {
        try {
          progressText.textContent = `{{ __('index-analyzer::index-analyzer.scanning') }}: ${completed}/${totalRoutes} {{ __('index-analyzer::index-analyzer.pages') }} (${Math.round((completed / totalRoutes) * 100)}%)`;

          // Sayfayı iframe'de yükle
          await loadPageInIframe(route);

          // Rotanın yanına işaret ekle
          const routeItems = routesList.querySelectorAll('div');
          routeItems.forEach(item => {
            if (item.textContent === route || item.textContent === '✅ ' + route) {
              item.innerHTML = '✅ ' + route;
            }
          });

          // Sorgu sayısını güncelle
          await updateQueryCount();

          // Güncel sorgu sayısını ve indeks sayılarını kontrol et
          try {
            const statsResponse = await fetch(`/${routePrefix}/get-stats`, {
              method: 'GET',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCSRFToken(),
                'X-Requested-With': 'XMLHttpRequest',
              },
            });

            if (statsResponse.ok) {
              const statsData = await statsResponse.json();
              // Sorgu sayısını güncelle (sadece değişmişse)
              if (statsData.queryCount && parseInt(document.getElementById('query-count').textContent) !== statsData.queryCount) {
                document.getElementById('query-count').textContent = statsData.queryCount;
                currentQueryCount = statsData.queryCount;
              }

              // Mevcut indeks sayısını güncelle (sadece değişmişse)
              if (statsData.existingIndexCount !== undefined &&
                  parseInt(document.getElementById('existing-index-count').textContent) !== statsData.existingIndexCount) {
                document.getElementById('existing-index-count').textContent = statsData.existingIndexCount;
              }

              // Yeni indeks sayısını güncelle (sadece değişmişse)
              if (statsData.newIndexCount !== undefined &&
                  parseInt(document.getElementById('new-index-count').textContent) !== statsData.newIndexCount) {
                document.getElementById('new-index-count').textContent = statsData.newIndexCount;
              }

              generateIndexesBtn.click();
            }
          } catch (statsError) {
            console.error('Stats error:', statsError);
          }

          completed++;
          progressBar.value = completed;
        } catch (error) {
          console.error(`Error crawling ${route}:`, error);
        }
      }

      // Son kez tüm istatistikleri güncelle
      await updateQueryCount();

      // Tarama tamamlandı mesajı
      progressText.textContent = '{{ __('index-analyzer::index-analyzer.scan_completed') }}';
      progressBar.value = totalRoutes;

      // Otomatik olarak generateIndexes butonuna tıkla
      generateIndexesBtn.click();

      // Önerilen ve mevcut indeksleri güncellemek için isteği tetikle
      try {
        // İndeks önerileri oluştur
        const indexSuggestionsResponse = await fetch(`/${routePrefix}/generate-suggestions`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
          },
        });

        if (indexSuggestionsResponse.ok) {
          const suggestionsData = await indexSuggestionsResponse.json();

          // İndeks sayılarını güncelle
          if (suggestionsData.existingIndexes && Array.isArray(suggestionsData.existingIndexes)) {
            document.getElementById('existing-index-count').textContent = suggestionsData.existingIndexes.length;
          }

          if (suggestionsData.newIndexes && Array.isArray(suggestionsData.newIndexes)) {
            document.getElementById('new-index-count').textContent = suggestionsData.newIndexes.length;
          }

          // İndeks bölümlerini göster
          const existingIndexesSection = document.querySelector('.existing-indexes-section');
          const newIndexesSection = document.querySelector('.new-indexes-section');
          const existingIndexesContainer = document.getElementById('existing-indexes');
          const newIndexesContainer = document.getElementById('new-indexes');

          // Sıfırla
          existingIndexesContainer.innerHTML = '';
          newIndexesContainer.innerHTML = '';

          // Var olan ve yeni indeksleri al
          const existingIndexes = suggestionsData.existingIndexes || [];
          const newIndexes = suggestionsData.newIndexes || [];

          // Var olan indeksler bölümü
          if (existingIndexes.length > 0) {
            existingIndexesSection.style.display = 'block';

            const existingStatementsText = existingIndexes.join('\n');
            existingIndexesContainer.innerHTML = `<pre>${existingStatementsText}</pre>`;

            // Var olan indeksler için toggle butonu
            const toggleExistingBtn = document.createElement('button');
            toggleExistingBtn.className = 'btn btn-sm btn-outline-secondary mt-2';
            toggleExistingBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_existing') }}';
            toggleExistingBtn.addEventListener('click', () => {
              const pre = existingIndexesContainer.querySelector('pre');
              if (pre.style.display === 'none') {
                pre.style.display = 'block';
                toggleExistingBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_existing') }}';
              } else {
                pre.style.display = 'none';
                toggleExistingBtn.innerHTML = '<i class="fa fa-eye"></i> {{ __('index-analyzer::index-analyzer.toggle_existing') }}';
              }
            });
            existingIndexesContainer.appendChild(toggleExistingBtn);
          } else {
            existingIndexesSection.style.display = 'block';
            existingIndexesContainer.innerHTML = '<div class="alert alert-info">{{ __('index-analyzer::index-analyzer.no_existing_indexes') }}</div>';
          }

          // Yeni indeksler bölümü
          if (newIndexes.length > 0) {
            newIndexesSection.style.display = 'block';

            const newStatementsText = newIndexes.join('\n');
            newIndexesContainer.innerHTML = `<pre>${newStatementsText}</pre>`;

            // Yeni indeksler için butonlar
            const buttonsContainer = document.createElement('div');
            buttonsContainer.className = 'mt-3';

            // Kopyalama butonu
            const copyBtn = document.createElement('button');
            copyBtn.className = 'btn btn-primary me-2';
            copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copy_statements') }}';
            copyBtn.addEventListener('click', () => {
              navigator.clipboard.writeText(newStatementsText).then(() => {
                copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copied') }}';
                setTimeout(() => {
                  copyBtn.textContent = '{{ __('index-analyzer::index-analyzer.copy_statements') }}';
                }, 2000);
              });
            });
            buttonsContainer.appendChild(copyBtn);

            // Toggle butonu
            const toggleNewBtn = document.createElement('button');
            toggleNewBtn.className = 'btn btn-sm btn-outline-secondary';
            toggleNewBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_new') }}';
            toggleNewBtn.addEventListener('click', () => {
              const pre = newIndexesContainer.querySelector('pre');
              if (pre.style.display === 'none') {
                pre.style.display = 'block';
                toggleNewBtn.innerHTML = '<i class="fa fa-eye-slash"></i> {{ __('index-analyzer::index-analyzer.toggle_new') }}';
              } else {
                pre.style.display = 'none';
                toggleNewBtn.innerHTML = '<i class="fa fa-eye"></i> {{ __('index-analyzer::index-analyzer.toggle_new') }}';
              }
            });
            buttonsContainer.appendChild(toggleNewBtn);

            newIndexesContainer.appendChild(buttonsContainer);
          } else {
            newIndexesSection.style.display = 'block';
            newIndexesContainer.innerHTML = '<div class="alert alert-info">{{ __('index-analyzer::index-analyzer.no_new_indexes') }}</div>';
          }
        }
      } catch (error) {
        console.error('İndeks önerileri oluşturma hatası:', error);
      }

      // Otomatik yenilemeyi durdur
      stopAutoRefresh();

      // 5 saniye sonra modalı kapat ve sayfayı yenile
      // Bu generateIndexesBtn'nin tıklanması ve işini yapması için yeterli süre sağlar
      setTimeout(() => {
        routesModal.style.display = 'none';
        // Sayfayı yenile
        window.location.reload();
      }, 5000);
    }

    // Tüm istatistikleri güncelleyen fonksiyon
    async function updateQueryCount() {
      try {
        const response = await fetch(`/${routePrefix}/get-stats`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (response.ok) {
          const data = await response.json();
          // Sorgu sayısını güncelle (sadece değişmişse)
          if (data.success && data.queryCount !== undefined &&
              parseInt(document.getElementById('query-count').textContent) !== data.queryCount) {
            document.getElementById('query-count').textContent = data.queryCount;
          }

          // Mevcut indeks sayısını güncelle (sadece değişmişse)
          if (data.existingIndexCount !== undefined &&
              parseInt(document.getElementById('existing-index-count').textContent) !== data.existingIndexCount) {
            document.getElementById('existing-index-count').textContent = data.existingIndexCount;
          }

          // Yeni indeks sayısını güncelle (sadece değişmişse)
          if (data.newIndexCount !== undefined &&
              parseInt(document.getElementById('new-index-count').textContent) !== data.newIndexCount) {
            document.getElementById('new-index-count').textContent = data.newIndexCount;
          }
        }
      } catch (error) {
        console.error('İstatistik güncelleme hatası:', error);
      }
    }

    function loadPageInIframe(url) {
      return new Promise((resolve) => {
        // İlk olarak fetch ile deneyelim
        fetch(url, {
          method: 'GET',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html',
          },
          credentials: 'same-origin',
        }).catch(() => {/* Hata olsa bile devam et */});

        // Şimdi iframe ile yükleyelim
        const iframe = document.createElement('iframe');
        iframe.style.position = 'absolute';
        iframe.style.left = '-9999px';
        iframe.style.width = '500px';
        iframe.style.height = '500px';
        document.body.appendChild(iframe);

        let iframeLoaded = false;

        iframe.addEventListener('load', () => {
          iframeLoaded = true;

          // JavaScript'in çalışması için biraz bekle
          setTimeout(() => {
            document.body.removeChild(iframe);
            resolve();
          }, 1500);
        });

        iframe.addEventListener('error', () => {
          document.body.removeChild(iframe);
          resolve();
        });

        // Zaman aşımı
        setTimeout(() => {
          if (!iframeLoaded) {
            document.body.removeChild(iframe);
            resolve();
          }
        }, 10000);

        iframe.src = url;
      });
    }
  });
</script>
<!-- Bootstrap ve Dil değiştirici script -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap dropdown'lar zaten otomatik başlatılır, manuel başlatmaya gerek yok

    // Dil dropdown menüsündeki tüm dil seçeneklerini al
    const languageItems = document.querySelectorAll('[data-language-code]');
    console.log('Dil seçenekleri:', languageItems.length);

    // Her dil seçeneğine tıklama olayı ekle
    languageItems.forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Dil seçeneği tıklandı');

        // Seçilen dil kodunu al
        const languageCode = this.getAttribute('data-language-code');
        console.log('Seçilen dil:', languageCode);

        // Rota önekini al
        const prefix = '{{ config("index-analyzer.route_prefix", "index-analyzer") }}';

        // URL oluştur
        const localeUrl = `/${prefix}/set-locale/${languageCode}`;
        console.log('Dil değiştirme URL:', localeUrl);

        // Mevcut URL'i sakla
        const currentUrl = window.location.href;

        // Form oluştur ve POST isteği gönder
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = localeUrl;

        // Geri dönüş URL'i ekle
        const redirectInput = document.createElement('input');
        redirectInput.type = 'hidden';
        redirectInput.name = 'redirect';
        redirectInput.value = currentUrl;
        form.appendChild(redirectInput);

        // CSRF token ekle
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        form.appendChild(csrfInput);

        // Form'u gizle ve gönder
        form.style.display = 'none';
        document.body.appendChild(form);
        form.submit();
      });
    });
  });
</script>
</body>
</html>
