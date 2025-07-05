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

    return '';
  },
};
