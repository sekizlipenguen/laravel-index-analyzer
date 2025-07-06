# Laravel Index Analyzer

📊 Otomatik SQL Index Öneri Sistemi

Laravel tabanlı projelerde kullanılan tüm SQL sorgularını gerçek kullanıcı deneyimi üzerinden (frontend navigasyon, AJAX istekleri dahil) toplayıp, eksik indeksleri tespit eden ve bunlara karşılık SQL önerileri sunan bir paket. Bu sayede veritabanı performansını artırarak, uygulamanızın daha hızlı çalışmasını sağlayabilirsiniz.

## Özellikler

- Tarayıcı entegrasyonu ile gerçek kullanıcı deneyimini simüle eder
- Otomatik JS DebugBar ile kolay kullanım
- Tüm SQL sorgularını analiz ederek eksik indeksleri tespit eder
- JOIN, WHERE, ORDER BY ve GROUP BY cümlelerini akıllıca analiz eder
- Önerilen indeksler için hazır SQL komutları sunar
- Aynı sorguların tekrar analiz edilmesini önleyen akıllı önbellek sistemi
- Kolay yapılandırma (.env ve config dosyası ile)
- Minimum sistem kaynağı kullanımı

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

2. Uygulamanızda geliştirme ortamında kullanmanız önerilir. Production ortamında kullanırken bu ayarı false yapmanız tavsiye edilir.

3. İki kullanım seçeneği vardır:

   **A. DebugBar ile kullanım:**
   - Tarayıcıda sayfalarınızı ziyaret ederken ekranın altında görünen debugbar'ı kullanın
   - "Tarama Başlat" butonu ile otomatik taramayı başlatın
   - Uygulamanın olabildiğince çok sayfasında gezinin (normal kullanıcı deneyimini simüle edin)
   - Giriş gerektiren sayfalara, admin panellerine, ve özel rotalara da giriş yaparak erişin
   - Tüm gezintileriniz bittiğinde "İndexleri Çıkar" butonuna tıklayarak analiz sonuçlarını görüntüleyin
   - Önerilen SQL komutlarını doğrudan kopyalayabilirsiniz

   **B. Kontrol Paneli ile kullanım:**
   - `/index-analyzer` adresine giderek kontrol paneline erişin (ör: `https://siteadi.com/index-analyzer`)
   - "Tarama Başlat" butonuna tıklayarak otomatik rotaları taramayı başlatın
   - Elle ek sayfalarda gezinti yapın (özellikle giriş gerektiren sayfalar için önemli)
   - "Analizleri Göster" butonuna tıklayarak detaylı SQL analizlerini görüntüleyin
   - "İndeks Önerileri" bölümünden hazır SQL kodlarını alabilirsiniz
   - "Dışa Aktar" butonu ile tüm analizleri JSON formatında kaydedebilirsiniz

## Nasıl Çalışır?

1. **Sorgu Toplama**:
   - Uygulamanızda gerçekleşen tüm SQL sorguları otomatik olarak yakalanır
   - Açık GET rotaları otomatik olarak taranabilir
   - Giriş gerektiren sayfaları manuel olarak gezdiğinizde bu sayfalardaki sorgular da kaydedilir
   - AJAX isteklerindeki sorgular dahil tüm veritabanı sorguları yakalanır
   - Tekrarlanan sorgular akıllıca filtrelenir

2. **Veri Analizi**:
   - Toplanan sorgular detaylı olarak analiz edilir
   - WHERE, JOIN, ORDER BY ve GROUP BY cümlelerindeki sütunlar tespit edilir
   - Performans etkisi yüksek olan sorgular belirlenir
   - Tekrar sayısı ve çalışma sürelerine göre sınıflandırılır

3. **İndeks Önerisi**:
   - Sık kullanılan ve performans etkisi olan sütunlar için en uygun indeks önerileri oluşturulur
   - Birleşik (composite) indeksler için akıllı analizler yapılır
   - Tablolar arası ilişkiler dikkate alınır
   - Mevcut indekslerle çakışmalar kontrol edilir

4. **SQL Üretimi**:
   - Önerilen indeksler için hazır SQL komutları oluşturulur
   - Doğrudan veritabanınıza uygulayabileceğiniz format sunulur
   - İndeks isimlendirmesi otomatik ve standartlara uygun şekilde yapılır

## Yapılandırma

`config/index-analyzer.php` dosyası ile paket davranışını özelleştirebilirsiniz:

```php
return [
    // IndexAnalyzer'ı aktif/pasif yapma (üretim ortamında false yapmanız önerilir)
    'enabled' => env('INDEX_ANALYZER_ENABLED', false),

    // Sorgu verilerinin depolanma yöntemi (memory veya file)
    'storage' => env('INDEX_ANALYZER_STORAGE', 'file'),

    // Log dosyası yolu
    'log_path' => storage_path('logs/index-analyzer.log'),

    // Maksimum log dosyası boyutu (10MB varsayılan)
    'max_log_size' => 10 * 1024 * 1024,

    // Rota öneki
    'route_prefix' => 'index-analyzer',

    // Yoksayılan tablolar (bu tablolar için öneri oluşturulmaz)
    'ignored_tables' => [
        'migrations',
        'jobs',
        'failed_jobs',
        'password_resets',
        'sessions',
        'personal_access_tokens',
        // Kendi tablolarınızı buraya ekleyebilirsiniz
    ],

    // Öneriler için ayarlar
    'suggestions' => [
        'min_query_time' => 0,  // Milisaniye cinsinden minimum sorgu süresi
        'min_query_count' => 1, // Minimum sorgu tekrar sayısı
    ],
];
```

## Performans Etkisi

Bu paket, geliştirme ortamında kullanılmak üzere tasarlanmıştır. Performans etkisini minimize etmek için:

- Aynı sorguları tekrar kaydetmemek için akıllı önbellek kullanır
- İhtiyaç duyulmadığında sistem kaynağı tüketmez
- Dosya depolaması seçeneği, bellek kullanımını azaltır
- Production ortamında kullanım önerilmez, kullanılacaksa `.env` dosyasında `INDEX_ANALYZER_ENABLED=false` ayarı yapılmalıdır

## Örnekler

### Örnek Analiz Çıktısı

```json
{
   "suggestions": [
      {
         "table": "users",
         "columns": [
            "email",
            "status"
         ],
         "query_count": 28,
         "avg_time": 3.45,
         "index_name": "users_email_status_idx"
      },
      {
         "table": "orders",
         "columns": [
            "user_id",
            "created_at"
         ],
         "query_count": 15,
         "avg_time": 12.7,
         "index_name": "orders_user_id_created_at_idx"
      }
   ],
   "statements": [
      "ALTER TABLE `users` ADD INDEX `users_email_status_idx` (`email`,`status`);",
      "ALTER TABLE `orders` ADD INDEX `orders_user_id_created_at_idx` (`user_id`,`created_at`);"
   ]
}
```

## Pratik Kullanım İpuçları

**En Etkili Kullanım İçin**

1. **Kapsamlı Gezinti Yapın**:
   - Uygulamanızın mümkün olduğunca çok sayfasında gezinin
   - Admin paneli, üye sayfaları gibi giriş gerektiren alanlara mutlaka giriş yapın
   - Filtrelemeleri, aramaları ve AJAX ile yüklenen içerikleri test edin
   - Raporlama, listeleme gibi ağır sorgu içeren sayfaları ziyaret edin

2. **Gerçek Kullanıcı Senaryoları**:
   - Gerçek kullanıcıların yapacağı işlemleri simüle edin
   - Sipariş verme, kayıt olma, ürün arama gibi tipik işlemleri gerçekleştirin
   - Her türlü filtre ve sıralama seçeneğini deneyin

3. **Otomatik Tarayıcıyı Doğru Kullanın**:
   - Otomatik tarayıcı sadece açık (giriş gerektirmeyen) rotaları tarar
   - Giriş gerektiren sayfalar için manuel olarak giriş yapıp gezinmeniz gerekir
   - Her işlemden sonra indeks önerilerini kontrol edin

## Sorun Giderme

**Tarayıcı taraması çalışmıyor**

- JavaScript konsolunda hata olup olmadığını kontrol edin
- Tarayıcınızın aynı kaynaktan JavaScript yüklemesine izin verdiğinden emin olun
- CORS politikalarının taramaya engel olmadığından emin olun

**Hiç sorgu kaydedilmiyor**

- `.env` dosyasında `INDEX_ANALYZER_ENABLED=true` ayarını kontrol edin
- Sorgu log klasörünün yazılabilir olduğundan emin olun
- Uygulama yetkilerini kontrol edin
- Laravel Debug Bar'ın yüklendiğinden emin olun

**Bazı sorgular analizde görünmüyor**

- `ignored_tables` ayarında ilgili tabloların olmadığından emin olun
- Çok hızlı çalışan sorgular için `min_query_time` değerini 0 olarak ayarlayın
- Bazı ORM sorguları gerçekte çalıştırılmamış olabilir (lazy loading)

## Katkıda Bulunma

Katkılarınızı bekliyoruz! Lütfen PR göndermeden önce şunları yapın:

1. Testlerinizi yazın
2. Kodunuzu PSR-12 standartlarına göre biçimlendirin
3. Açıklayıcı commit mesajları kullanın

## Lisans

MIT lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakınız.
