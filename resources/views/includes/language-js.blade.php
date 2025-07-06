<script>
  window.IndexAnalyzer = window.IndexAnalyzer || {};
  window.IndexAnalyzer.translations = {
    // Genel
    title: "{{ __('index-analyzer.title') }}",
    description: "{{ __('index-analyzer.description') }}",

    // Navigasyon
    dashboard: "{{ __('index-analyzer.dashboard') }}",
    scan: "{{ __('index-analyzer.scan') }}",
    suggestions: "{{ __('index-analyzer.suggestions') }}",
    settings: "{{ __('index-analyzer.settings') }}",
    language: "{{ __('index-analyzer.language') }}",

    // Aksiyonlar
    start_scan: "{{ __('index-analyzer.start_scan') }}",
    extract_indexes: "{{ __('index-analyzer.extract_indexes') }}",
    clear_queries: "{{ __('index-analyzer.clear_queries') }}",
    copy_statements: "{{ __('index-analyzer.copy_statements') }}",

    // Mesajlar
    crawling_started: "{{ __('index-analyzer.crawling_started') }}",
    no_queries_recorded: "{{ __('index-analyzer.no_queries_recorded') }}",
    queries_cleared: "{{ __('index-analyzer.queries_cleared') }}",
    query_recorded: "{{ __('index-analyzer.query_recorded') }}",

    // Kontrol Paneli
    total_queries: "{{ __('index-analyzer.total_queries') }}",
    total_suggestions: "{{ __('index-analyzer.total_suggestions') }}",
    scanned_routes: "{{ __('index-analyzer.scanned_routes') }}",
    slow_queries: "{{ __('index-analyzer.slow_queries') }}",

    // Öneriler
    table: "{{ __('index-analyzer.table') }}",
    columns: "{{ __('index-analyzer.columns') }}",
    index_name: "{{ __('index-analyzer.index_name') }}",
    statements: "{{ __('index-analyzer.statements') }}",
    apply_to_database: "{{ __('index-analyzer.apply_to_database') }}",

    // Hata Ayıklama
    debug_info: "{{ __('index-analyzer.debug_info') }}",
    query_count: "{{ __('index-analyzer.query_count') }}",
    sample_queries: "{{ __('index-analyzer.sample_queries') }}",
  };

  window.IndexAnalyzer.currentLocale = "{{ App::getLocale() }}";
  window.IndexAnalyzer.supportedLocales = {!! json_encode(config('language.locales', ['en' => 'English', 'tr' => 'Türkçe'])) !!};
</script>
<script>
  window.IndexAnalyzer = window.IndexAnalyzer || {};
  window.IndexAnalyzer.translations = {
    // Genel
    title: "{{ __('index-analyzer.title') }}",
    description: "{{ __('index-analyzer.description') }}",

    // Navigasyon
    dashboard: "{{ __('index-analyzer.dashboard') }}",
    scan: "{{ __('index-analyzer.scan') }}",
    suggestions: "{{ __('index-analyzer.suggestions') }}",
    settings: "{{ __('index-analyzer.settings') }}",
    language: "{{ __('index-analyzer.language') }}",

    // Aksiyonlar
    start_scan: "{{ __('index-analyzer.start_scan') }}",
    extract_indexes: "{{ __('index-analyzer.extract_indexes') }}",
    clear_queries: "{{ __('index-analyzer.clear_queries') }}",
    copy_statements: "{{ __('index-analyzer.copy_statements') }}",

    // Mesajlar
    crawling_started: "{{ __('index-analyzer.crawling_started') }}",
    no_queries_recorded: "{{ __('index-analyzer.no_queries_recorded') }}",
    queries_cleared: "{{ __('index-analyzer.queries_cleared') }}",
    query_recorded: "{{ __('index-analyzer.query_recorded') }}",

    // Kontrol Paneli
    total_queries: "{{ __('index-analyzer.total_queries') }}",
    total_suggestions: "{{ __('index-analyzer.total_suggestions') }}",
    scanned_routes: "{{ __('index-analyzer.scanned_routes') }}",
    slow_queries: "{{ __('index-analyzer.slow_queries') }}",

    // Öneriler
    table: "{{ __('index-analyzer.table') }}",
    columns: "{{ __('index-analyzer.columns') }}",
    index_name: "{{ __('index-analyzer.index_name') }}",
    statements: "{{ __('index-analyzer.statements') }}",
    apply_to_database: "{{ __('index-analyzer.apply_to_database') }}",

    // Hata Ayıklama
    debug_info: "{{ __('index-analyzer.debug_info') }}",
    query_count: "{{ __('index-analyzer.query_count') }}",
    sample_queries: "{{ __('index-analyzer.sample_queries') }}",
  };

  window.IndexAnalyzer.currentLocale = "{{ App::getLocale() }}";
  window.IndexAnalyzer.supportedLocales = {!! json_encode(config('language.locales', ['en' => 'English', 'tr' => 'Türkçe'])) !!};
</script>
