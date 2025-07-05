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

        // Trait kullanımlarını bul
        return preg_replace_callback(
            '/\(new\s+class[^{]*{[^}]*use\s+([\w\\]+);/is',
            function ($matches) {
                $trait = $matches[1];
                return str_replace(
                    "use {$trait};",
                    "/* use {$trait}; */ // Devre dışı bırakıldı",
                    $matches[0]
                );
            },
            $query
        );
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
