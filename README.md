# Laravel Index Analyzer

📊 Otomatik SQL Index Öneri Sistemi

Laravel tabanlı projelerde kullanılan tüm SQL sorgularını gerçek kullanıcı deneyimi üzerinden (frontend navigasyon, AJAX istekleri dahil) toplayıp, eksik index'leri tespit eden ve bunlara karşılık SQL önerileri sunan bir paket.

## Özellikler

- Tarayıcı entegrasyonu ile gerçek kullanıcı deneyimini simüle eder
- Otomatik JS DebugBar ile kolay kullanım
- Tüm SQL sorgularını analiz ederek eksik indeksleri tespit eder
- Önerilen indeksler için SQL komutları sunar
- Kolay yapılandırma (.env ve config dosyası ile)

## Kurulum

```bash
composer require sekizlipenguen/laravel-index-analyzer
```

Config dosyasını yayınlamak için:

```bash
php artisan vendor:publish --provider="SekizliPenguen\IndexAnalyzer\IndexAnalyzerServiceProvider"
```

## Kullanım

1. `.env` dosyanıza aşağıdaki ayarı ekleyin:

```
INDEX_ANALYZER_ENABLED=true
```

2. Uygulamanızda debugbar'ı görmek için sadece geliştirme ortamında kullanmanız önerilir.

3. Tarayıcıda sayfalarınızı ziyaret ederken debugbar'ı kullanarak:
   - "Tarama Başlat" butonu ile otomatik taramayı başlatın
   - "İndexleri Çıkar" butonu ile önerileri görüntüleyin

## Yapılandırma

`config/index-analyzer.php` dosyası ile özelleştirme yapabilirsiniz:

```php
// Detaylı yapılandırma seçenekleri için config dosyasına bakınız
```

## Lisans

MIT lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakınız.
