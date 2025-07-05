# Laravel Index Analyzer 🔍

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sekizlipenguen/laravel-index-analyzer.svg)](https://packagist.org/packages/sekizlipenguen/laravel-index-analyzer)
[![Total Downloads](https://img.shields.io/packagist/dt/sekizlipenguen/laravel-index-analyzer.svg)](https://packagist.org/packages/sekizlipenguen/laravel-index-analyzer)
![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-9.0%2B-orange)

Laravel Index Analyzer, veritabanı sorgu performansını optimize etmek için eksik indeksleri otomatik olarak tespit eden güçlü bir Laravel komut aracıdır. AST (Abstract Syntax Tree) analizi kullanarak projenizde tüm Eloquent ve DB sorgu zincirlerini tarar ve veritabanı şemanızı inceleyerek eksik indeksleri belirler.

## Özellikler

- 🧠 **AST Tabanlı Analiz**: Projenin tüm PHP dosyalarını tarar ve tüm veritabanı sorgularını tespit eder
- 🔍 **Akıllı İndeks Tespiti**: WHERE, JOIN, ORDER BY ve GROUP BY kullanılan sütunları tespit eder
- 📊 **Etki Analizi**: Önerilen indeksler için tablo boyutu, kardinalite ve etki analizini gösterir
- 🧩 **Kompozit İndeks Önerileri**: Birlikte kullanılan sütunlar için kompozit indeks önerileri oluşturur
- 🛠️ **Otomatik Uygulama**: Tespit edilen indeksleri otomatik olarak uygulayabilir
- 💾 **SQL Dışa Aktarım**: Önerilen indeksleri SQL dosyası olarak dışa aktarabilir
- 📋 **Ayrıntılı Raporlama**: Performans optimizasyonları için kapsamlı raporlar sunar

## Kurulum

Composer ile kurulum yapabilirsiniz:

```bash
composer require sekizlipenguen/laravel-index-analyzer
```

Konfigürasyon dosyasını yayınlamak için (opsiyonel):

```bash
php artisan vendor:publish --provider="SekizliPenguen\IndexAnalyzer\IndexAnalyzerServiceProvider"
```

## Kullanım

Ana komut şu şekildedir:

```bash
php artisan optimize:index
```

### Seçenekler

- `--execute`: Önerilen indeksleri otomatik olarak uygular
- `--sql=dosya_adi.sql`: Ekran yerine belirtilen dosyaya SQL çıktısı verir
- `--dry-run`: Simülasyon modu - hiçbir şey çalıştırmaz veya kaydetmez
- `--model=User`: Sadece belirli bir modeli eşleştiren sorguları tarar
- `--impact-analysis`: İndeks önerileri için etki analizi yapar
- `--composite`: Kompozit indeks önerileri oluşturur
- `--cardinality-threshold=25`: Kardinalite analizi için eşik değerini belirler
- `--help`: Tüm seçenekleri görüntüler

### Örnekler

Tüm proje sorgularını analiz edip önerileri görmek için:

```bash
php artisan optimize:index
```

Detaylı etki analizi yapmak için:

```bash
php artisan optimize:index --impact-analysis
```

Kompozit indeks önerileri de dahil olmak üzere tüm önerileri SQL dosyasına aktarmak için:

```bash
php artisan optimize:index --sql=database/missing-indexes.sql --composite
```

Önerilen tüm indeksleri otomatik olarak uygulamak için:

```bash
php artisan optimize:index --execute
```

## Yapılandırma

`config/index-analyzer.php` dosyasında araç ayarlarını özelleştirebilirsiniz:

```php
return [
    'enabled' => true,
    'scan_path' => 'base_path',
    'exclude' => [
        'vendor',
        'node_modules',
        'storage',
        'tests',
    ],
    'model_paths' => [
        'app/Models',
    ],
    // Diğer ayarlar...
];
```

## Nasıl Çalışır?

1. Kod tarama: PHP-Parser kullanarak projenizin kaynak kodunu tarar
2. Sorgu tespiti: Model sorguları ve DB ifadelerini algılar
3. SQL analizi: Tespit edilen sorguları SQL'e dönüştürür
4. İndeks analizi: Eksik indeksleri belirler
5. Optimizasyon: Gerekli indeksleri önerir veya uygular
6. Sonuçları ekranda gösterir veya SQL dosyasına aktarır

## Katkıda Bulunma

Katkılarınızı memnuniyetle karşılıyoruz! Lütfen bir pull request göndermeden önce test etmeyi unutmayın.

## Yapılandırma

`config/index-analyzer.php` dosyasında araç ayarlarını özelleştirebilirsiniz:

```php
return [
    'enabled' => true,
    'scan_path' => 'base_path',
    'exclude' => [
        'vendor',
        'node_modules',
        'storage',
        'tests',
    ],
    'model_paths' => [
        'app/Models',
    ],
    // Diğer ayarlar...
];
```

## Nasıl Çalışır?

1. Kod tarama: PHP-Parser kullanarak projenizin kaynak kodunu tarar
2. Sorgu tespiti: Model sorguları ve DB ifadelerini algılar
3. SQL analizi: Tespit edilen sorguları SQL'e dönüştürür
4. İndeks analizi: Eksik indeksleri belirler
5. Optimizasyon: Gerekli indeksleri önerir veya uygular

## Katkıda Bulunma

Katkılarınızı memnuniyetle karşılıyoruz! Lütfen bir pull request göndermeden önce test etmeyi unutmayın.

## Lisans

MIT Lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakın.

---

Sekizli Penguen tarafından ❤️ ile geliştirilmiştir.
