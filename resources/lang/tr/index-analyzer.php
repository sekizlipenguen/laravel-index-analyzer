<?php

return [
    // Genel
    'title' => 'İndeks Analizörü',
    'description' => 'SQL sorgularınızı analiz edin ve indeks önerileri alın',

    // Navigasyon
    'dashboard' => 'Kontrol Paneli',
    'scan' => 'Tarama',
    'suggestions' => 'Öneriler',
    'settings' => 'Ayarlar',
    'language' => 'Dil',

    // Aksiyonlar
    'start_scan' => 'Tarama Başlat',
    'extract_indexes' => 'İndeksleri Çıkar',
    'clear_queries' => 'Tüm Sorguları Temizle',
    'clear_all' => 'Temizle',
    'refresh_queries' => 'Sorguları Yenile',
    'copy_statements' => 'SQL Komutlarını Kopyala',
    'show' => 'Göster',
    'hide' => 'Gizle',

    // Mesajlar
    'crawling_started' => 'Tarama başlatıldı',
    'no_queries_recorded' => 'Hiç sorgu kaydedilmemiş. Lütfen önce tarama yapın.',
    'queries_cleared' => 'Tüm sorgular temizlendi',
    'query_recorded' => 'Sorgu kaydedildi',
    'confirm_clear_queries' => 'Tüm kaydedilen sorguları temizlemek istediğinize emin misiniz?',
    'scanning' => 'Taranıyor',
    'pages' => 'sayfa',
    'scan_completed' => 'Tarama tamamlandı!',
    'scan_starting' => 'Tarama başlatılıyor...',
    'ready' => 'Hazır',
    'refreshing_queries' => 'Sorgular yenileniyor...',
    'queries_refreshed' => 'Sorgular yenilendi',
    'generating_suggestions' => 'İndeks önerileri oluşturuluyor...',
    'no_suggestions' => 'Önerilen indeks bulunamadı.',
    'error' => 'Hata',
    'unknown_error' => 'Bilinmeyen hata',
    'stats_refresh_error' => 'İstatistik yenileme hatası',
    'generating_index_suggestions' => 'İndeks önerileri oluşturuluyor...',

    // Kontrol Paneli
    'total_queries' => 'Toplam Sorgu',
    'total_suggestions' => 'Öneriler',
    'total_existing_indexes' => 'Mevcut İndeksler',
    'total_new_indexes' => 'Önerilen İndeksler',
    'scanned_routes' => 'Taranan Rotalar',
    'slow_queries' => 'Yavaş Sorgular',
    'time_ms' => 'Süre (ms)',
    'date' => 'Tarih',
    'suggestions_hint' => 'İndeks önerilerini görmek için önce bir tarama yapın ve ardından "İndeksleri Çıkar" butonuna tıklayın.',
    'routes_to_scan' => 'Taranacak Rotalar',
    'routes_to_scan_desc' => 'Aşağıdaki rotalar otomatik olarak taranacak ve SQL sorguları kaydedilecek:',

    // Öneriler
    'table' => 'Tablo',
    'columns' => 'Sütunlar',
    'index_name' => 'İndeks Adı',
    'statements' => 'SQL Komutları',
    'apply_to_database' => 'Veritabanına Uygula',
    'copied' => 'Kopyalandı!',
    'existing_indexes' => 'Mevcut İndeksler',
    'new_indexes' => 'Önerilen Yeni İndeksler',
    'no_existing_indexes' => 'Önerilen sütunlar için mevcut indeks bulunamadı.',
    'no_new_indexes' => 'Yeni indeks önerisi bulunamadı.',
    'all_indexes_exist' => 'Tüm önerilen indeksler zaten veritabanında mevcut.',
    'toggle_existing' => 'Mevcut İndeksleri Göster/Gizle',
    'toggle_new' => 'Yeni Önerileri Göster/Gizle',
    'status' => 'Durum',
    'already_exists' => 'Zaten Mevcut',
    'new' => 'Yeni',
    'suggested_indexes' => 'Önerilen İndeksler',
    'existing_indexes_desc' => 'Aşağıdaki indeksler zaten veritabanınızda bulunmaktadır:',
    'new_indexes_desc' => 'Aşağıdaki indekslerin eklenmesi önerilmektedir:',
    'index_exists' => 'İndeks Mevcut',
    'index_new' => 'Yeni İndeks',
    'index_status' => 'İndeks Durumu',
    'suggested_for' => 'Şunun için önerildi:',
    'create_all_new' => 'Tüm Yeni İndeksleri Oluştur',
    'create_selected' => 'Seçili İndeksleri Oluştur',

    // Hata Ayıklama
    'debug_info' => 'Hata Ayıklama Bilgisi',
    'query_count' => 'Sorgu Sayısı',
    'sample_queries' => 'Örnek Sorgular',
    'page' => 'sayfa',
    'scan_started' => 'Tarama başlatıldı',
    'query_count_update_error' => 'Sorgu sayısı güncelleme hatası',
    'fetch_error' => 'Fetch hatası',
    'stats_refresh_error' => 'İstatistik yenileme hatası'
];
