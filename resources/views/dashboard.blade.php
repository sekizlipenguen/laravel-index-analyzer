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
        <h2 class="card-title">Genel Bakış</h2>
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value">{{ $queryCount }}</div>
                <div class="stat-label">Kaydedilen Sorgu</div>
            </div>
        </div>
    </div>

    <div class="dashboard-card">
        <h2 class="card-title">Son Kaydedilen Sorgular</h2>
        <table>
            <thead>
            <tr>
                <th>SQL</th>
                <th>Süre (ms)</th>
                <th>Tarih</th>
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
                    <td colspan="3">Henüz kaydedilmiş sorgu bulunmuyor.</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    <div class="dashboard-card">
        <h2 class="card-title">İndeks Önerileri</h2>
        <p>İndeks önerilerini görmek için önce bir tarama yapın ve ardından "İndeksleri Çıkar" butonuna tıklayın.</p>
        <div id="results"></div>
    </div>
</div>

<!-- Tarama Modalı -->
<div id="routesModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Taranacak Rotalar</h2>
        <p>Aşağıdaki rotalar otomatik olarak taranacak ve SQL sorguları kaydedilecek:</p>
        <div class="routes-list" id="routesList"></div>
        <div id="crawlProgress">
            <p id="progressText">Tarama başlatılıyor...</p>
            <progress id="progressBar" value="0" max="100" style="width: 100%"></progress>
        </div>
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
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

    function getCSRFToken() {
      return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    startCrawlBtn.addEventListener('click', async function() {
      try {
        startCrawlBtn.disabled = true;

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
        resultsElement.textContent = 'İndeks önerileri oluşturuluyor...';

        const response = await fetch(`/${routePrefix}/generate-suggestions`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
          },
        });

        const data = await response.json();

        if (data.success) {
          if (data.statements.length > 0) {
            const statementsText = data.statements.join('\n');
            resultsElement.innerHTML = `<pre>${statementsText}</pre>`;

            // Add copy button
            const copyBtn = document.createElement('button');
            copyBtn.className = 'btn btn-primary';
            copyBtn.textContent = 'Kopyala';
            copyBtn.style.marginTop = '10px';
            copyBtn.addEventListener('click', () => {
              navigator.clipboard.writeText(statementsText).then(() => {
                copyBtn.textContent = 'Kopyalandı!';
                setTimeout(() => {
                  copyBtn.textContent = 'Kopyala';
                }, 2000);
              });
            });
            resultsElement.appendChild(copyBtn);

            // Add debug info
            if (data.debug && data.debug.query_count) {
              const debugInfo = document.createElement('div');
              debugInfo.innerHTML = `<br><small>Analiz edilen sorgu sayısı: ${data.debug.query_count}</small>`;
              resultsElement.appendChild(debugInfo);
            }
          } else {
            resultsElement.textContent = 'Önerilen indeks bulunamadı.' + (data.message ? ' ' + data.message : '');
          }
        } else {
          resultsElement.textContent = 'Hata: ' + (data.message || 'Bilinmeyen hata');
        }
      } catch (error) {
        resultsElement.textContent = 'Hata: ' + error.message;
        console.error('Generate indexes error:', error);
      } finally {
        generateIndexesBtn.disabled = false;
      }
    });

    clearQueriesBtn.addEventListener('click', async function() {
      if (!confirm('Tüm kaydedilen sorguları temizlemek istediğinize emin misiniz?')) {
        return;
      }

      try {
        clearQueriesBtn.disabled = true;

        const response = await fetch(`/${routePrefix}/clear-queries`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken(),
          },
        });

        const data = await response.json();

        if (data.success) {
          alert('Tüm sorgular temizlendi');
          window.location.reload();
        } else {
          alert('Hata: ' + (data.message || 'Bilinmeyen hata'));
        }
      } catch (error) {
        alert('Hata: ' + error.message);
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
    });

    async function crawlRoutes(routes) {
      const totalRoutes = routes.length;
      let completed = 0;
      progressBar.max = totalRoutes;
      progressBar.value = 0;

      // Fetch ve iframe birlikte kullanacağız
      for (const route of routes) {
        try {
          progressText.textContent = `Taranıyor: ${completed}/${totalRoutes} sayfa (${Math.round((completed / totalRoutes) * 100)}%)`;

          // Sayfayı iframe'de yükle
          await loadPageInIframe(route);

          completed++;
          progressBar.value = completed;
        } catch (error) {
          console.error(`Error crawling ${route}:`, error);
        }
      }

      progressText.textContent = 'Tarama tamamlandı!';
      progressBar.value = totalRoutes;

      // 2 saniye sonra modalı kapat
      setTimeout(() => {
        routesModal.style.display = 'none';
        // Sayfayı yenile
        window.location.reload();
      }, 2000);
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
          }, 2000);
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

        // GET isteği ile dil değiştirme - en basit ve güvenilir yöntem
        window.location.href = `/${prefix}/set-locale/${languageCode}`;
      });
    });
  });
</script>
</body>
</html>
