<?php

namespace SekizliPenguen\IndexAnalyzer\Helpers;

class QuerySanitizer
{
    /**
     * Sorguları güvenli hale getiren metod
     *
     * @param string $query Orijinal sorgu
     * @return string Temizlenmiş ve güvenli sorgu
     */
    public static function sanitize(string $query): string
    {
        // Anonim sınıf içindeki trait kullanımlarını devre dışı bırak
        $query = static::disableAnonymousClassTraits($query);

        // Diğer tehlikeli yapıları temizle
        $query = static::sanitizeClosures($query);

        return $query;
    }

    /**
     * İmport'ları çakışmasını önlemek için alias verir
     *
     * @param array $imports İmport listesi
     * @return array Alias verilmiş import listesi
     */
    public static function processImportsWithAlias(array $imports): array
    {
        $result = [];
        $usedNames = [];
        $duplicates = [];

        // Önce çakışan isimleri tespit et
        foreach ($imports as $import) {
            if (empty($import)) continue;

            // use ifadesini temizle
            if (preg_match('/use\s+([^;]+);/', $import, $matches)) {
                $namespace = trim($matches[1]);

                // Sınıf adını al
                $parts = explode('\\', $namespace);
                $className = end($parts);

                // Eğer zaten bu isimde bir import varsa, çakışma listesine ekle
                if (isset($usedNames[$className])) {
                    $duplicates[$className][] = $namespace;
                } else {
                    $usedNames[$className] = $namespace;
                }
            }
        }

        // Şimdi import'ları yeniden oluştur, çakışanlara alias ekle
        foreach ($imports as $import) {
            if (empty($import)) continue;

            if (preg_match('/use\s+([^;]+);/', $import, $matches)) {
                $namespace = trim($matches[1]);
                $parts = explode('\\', $namespace);
                $className = end($parts);

                // Eğer bu isim çakışıyorsa alias ekle
                if (isset($duplicates[$className])) {
                    // Alias olarak son iki parçayı kullan
                    $alias = '';
                    if (count($parts) >= 2) {
                        $secondLast = $parts[count($parts) - 2];
                        $alias = $secondLast . $className;
                    } else {
                        $alias = 'Alias' . $className;
                    }

                    $result[] = "use {$namespace} as {$alias};";
                } else {
                    $result[] = $import;
                }
            } else {
                $result[] = $import; // İmport değilse olduğu gibi ekle
            }
        }

        return $result;
    }

    /**
     * Anonim sınıf içindeki trait kullanımlarını yorum satırı haline getirir
     *
     * @param string $query Orijinal sorgu
     * @return string Temizlenmiş sorgu
     */
    public static function disableAnonymousClassTraits(string $query): string
    {
        if (strpos($query, 'new class') === false) {
            return $query;
        }

        // Parantez sorunu için daha güvenilir bir regex yaklaşımı kullan
        $pattern = '/(\()?new\s+class[^{]*{[^}]*use\s+([\w\\]+);/is';

        if (preg_match($pattern, $query)) {
            $query = preg_replace_callback(
                $pattern,
                function ($matches) {
                    $openParen = $matches[1] ?? '';
                    $trait = $matches[2];

                    // Orijinal metni al ve trait kullanımını yorum satırı yap
                    $original = $matches[0];
                    $replacement = str_replace(
                        "use {$trait};",
                        "/* use {$trait}; */ // Devre dışı bırakıldı",
                        $original
                    );

                    return $replacement;
                },
                $query
            );

            // Null değer dönmesini engelle
            if ($query === null) {
                // Regex eşleşmesi başarısız olursa, orijinal sorguyu döndür
                return $query;
            }
        }

        return $query;
    }

    /**
     * Closure ve callable yapıları güvenli hale getirir
     *
     * @param string $query Orijinal sorgu
     * @return string Temizlenmiş sorgu
     */
    public static function sanitizeClosures(string $query): string
    {
        // İçteki closure'ları basitleştir
        $pattern = '/->([\w]+)\(function\s*\([^\)]*\)\s*(?:use\s*\([^\)]*\))?\s*{[^}]*}/is';

        // Eğer closure'lar varsa, basit bir callback ile değiştir
        if (preg_match($pattern, $query)) {
            return preg_replace(
                $pattern,
                '->$1(function() { return true; })',
                $query
            );
        }

        return $query;
    }
}
