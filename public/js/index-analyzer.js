/**
 * Laravel Index Analyzer JavaScript
 */

// Global storage for AJAX query information
window.laravelIndexAnalyzer = window.laravelIndexAnalyzer || {
  routePrefix: 'index-analyzer',
  enabled: false,
  captureXhr: true,
  captureFetch: true,
  initialize: function(options) {
    this.enabled = options.enabled || false;
    this.routePrefix = options.routePrefix || 'index-analyzer';
    this.captureXhr = options.captureXhr !== false;
    this.captureFetch = options.captureFetch !== false;

    if (this.enabled) {
      this.setupXhrInterceptor();
      this.setupFetchInterceptor();
    }
  },

  setupXhrInterceptor: function() {
    if (!this.captureXhr) return;

    const self = this;
    const originalXhrOpen = XMLHttpRequest.prototype.open;
    const originalXhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function() {
      this._iaMethod = arguments[0];
      this._iaUrl = arguments[1];
      this._iaStartTime = 0;
      return originalXhrOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function() {
      if (self.enabled && this._iaMethod && this._iaUrl) {
        this._iaStartTime = performance.now();

        const originalOnReadyStateChange = this.onreadystatechange;
        this.onreadystatechange = function() {
          if (this.readyState === 4) {
            const duration = performance.now() - this._iaStartTime;

            try {
              // If we can detect SQL in the response, record it
              const responseData = JSON.parse(this.responseText);
              if (responseData.sql) {
                self.recordQuery(this._iaUrl, responseData.sql, duration);
              }
            } catch (e) {
              // Ignore parsing errors
            }
          }

          if (originalOnReadyStateChange) {
            originalOnReadyStateChange.apply(this, arguments);
          }
        };
      }

      return originalXhrSend.apply(this, arguments);
    };
  },

  setupFetchInterceptor: function() {
    if (!this.captureFetch || !window.fetch) return;

    const self = this;
    const originalFetch = window.fetch;

    window.fetch = function() {
      const url = arguments[0];
      const options = arguments[1] || {};
      const startTime = performance.now();

      return originalFetch.apply(this, arguments).then(function(response) {
        if (!self.enabled) return response;

        const duration = performance.now() - startTime;
        const clonedResponse = response.clone();

        clonedResponse.json().then(function(data) {
          if (data.sql) {
            self.recordQuery(url, data.sql, duration);
          }
        }).catch(() => {});

        return response;
      });
    };
  },

  recordQuery: function(url, sql, time) {
    if (!this.enabled) return;

    // Translations
    const translations = window.IndexAnalyzer.translations;
    const currentLocale = window.IndexAnalyzer.currentLocale;

    // Update UI with translations
    updateUITranslations();

    fetch(`/${this.routePrefix}/record-query`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': this.getCSRFToken(),
      },
      body: JSON.stringify({
        url: url,
        sql: sql,
        time: time,
      }),
    }).catch(function(error) {
      console.error('Error recording query:', error);
    });
  },

  getCSRFToken: function() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
      return metaToken.getAttribute('content');
    }

    const tokenInput = document.querySelector('input[name="_token"]');
    if (tokenInput) {
      return tokenInput.value;
    }

    function updateUITranslations() {
      // Butonları güncelle
      if (document.getElementById('btnStartCrawl')) {
        document.getElementById('btnStartCrawl').textContent = translations.start_scan;
      }

      if (document.getElementById('btnGenerateSuggestions')) {
        document.getElementById('btnGenerateSuggestions').textContent = translations.extract_indexes;
      }

      if (document.getElementById('btnClearQueries')) {
        document.getElementById('btnClearQueries').textContent = translations.clear_queries;
      }

      if (document.getElementById('btnCopyStatements')) {
        document.getElementById('btnCopyStatements').textContent = translations.copy_statements;
      }

      // Dashboard metinlerini güncelle
      const elementsToUpdate = {
        'totalQueriesLabel': translations.total_queries,
        'totalSuggestionsLabel': translations.total_suggestions,
        'scannedRoutesLabel': translations.scanned_routes,
        'slowQueriesLabel': translations.slow_queries,
        'suggestionsTitle': translations.suggestions,
        'tableColumnHeader': translations.table,
        'columnsColumnHeader': translations.columns,
        'indexNameColumnHeader': translations.index_name,
        'statementsHeader': translations.statements,
        'debugInfoHeader': translations.debug_info,
        'queryCountLabel': translations.query_count,
        'sampleQueriesLabel': translations.sample_queries,
      };

      // Her elementi güncelle
      for (const [id, text] of Object.entries(elementsToUpdate)) {
        const element = document.getElementById(id);
        if (element) {
          element.textContent = text;
        }
      }
    }

    return '';
  },
};
