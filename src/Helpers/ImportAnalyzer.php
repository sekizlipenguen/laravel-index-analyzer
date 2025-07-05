<?php

namespace SekizliPenguen\IndexAnalyzer\Helpers;

class ImportAnalyzer
{
    /**
     * Dosyadaki namespace'i ve use ifadelerini analiz eder
     *
     * @param string $filePath Dosya yolu
     * @return array Tespit edilen importlar ve namespace
     */
    public static function analyzeFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'namespace' => null,
                'imports' => [],
                'traits' => [],
            ];
        }

        $content = file_get_contents($filePath);
        return self::analyzeContent($content);
    }

    /**
     * Dosya içeriğindeki namespace'i ve use ifadelerini analiz eder
     *
     * @param string $content Dosya içeriği
     * @return array Tespit edilen importlar ve namespace
     */
    public static function analyzeContent(string $content): array
    {
        $result = [
            'namespace' => null,
            'imports' => [],
            'traits' => [],
        ];

        // Namespace'i bul
        preg_match('/namespace\s+([^;]+);/i', $content, $nsMatch);
        $result['namespace'] = $nsMatch[1] ?? null;

        // Use ifadelerini bul
        preg_match_all('/^use\s+([^;{]+);/m', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                // as ifadeleri
                if (str_contains($match, ' as ')) {
                    list($class, $alias) = explode(' as ', $match);
                    $result['imports'][$alias] = trim($class);
                } else {
                    $parts = explode('\\', $match);
                    $className = end($parts);
                    $result['imports'][$className] = trim($match);
                }
            }
        }

        // Sınıf içi trait kullanımlarını bul
        preg_match_all('/class\s+[\w]+[^{]*{[^}]*use\s+([\w\\,\s]+);/is', $content, $traitMatches);
        if (!empty($traitMatches[1])) {
            foreach ($traitMatches[1] as $traitList) {
                $traits = array_map('trim', explode(',', $traitList));
                foreach ($traits as $trait) {
                    $result['traits'][] = $trait;

                    // Eğer trait tam yol içermiyorsa ve namespace varsa
                    if (!str_contains($trait, '\\') && $result['namespace']) {
                        // Önce importlar içinde arayalım
                        if (isset($result['imports'][$trait])) {
                            // Zaten import edilmiş
                        } else {
                            // Namespace içinde olabilir
                            $result['imports'][$trait] = $result['namespace'] . '\\' . $trait;
                        }
                    } elseif (str_contains($trait, '\\')) {
                        // Tam yol belirtilmiş, son kısmını anahtar olarak ekleyelim
                        $parts = explode('\\', $trait);
                        $className = end($parts);
                        $result['imports'][$className] = trim($trait);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * İçerikteki sınıf adlarını ve trait kullanımlarını çıkarır
     *
     * @param string $content Kod içeriği
     * @return array Tespit edilen sınıflar ve trait'ler
     */
    public static function extractClassAndTraitUsages(string $content): array
    {
        $result = [
            'classes' => [],
            'traits' => [],
        ];

        // new Sınıf şeklinde kullanımlar
        preg_match_all('/new\s+([\\\w]+)/i', $content, $classMatches);
        if (!empty($classMatches[1])) {
            foreach ($classMatches[1] as $class) {
                $result['classes'][] = trim($class);
            }
        }

        // StaticCall sınıfları (Sınıf::metod)
        preg_match_all('/([\\\w]+)::/i', $content, $staticMatches);
        if (!empty($staticMatches[1])) {
            foreach ($staticMatches[1] as $static) {
                if (!in_array($static, ['self', 'static', 'parent']) && !str_starts_with($static, '$')) {
                    $result['classes'][] = trim($static);
                }
            }
        }

        // use TraitAdı şeklinde kullanımlar
        preg_match_all('/use\s+([\w\\]+)(?:\s*;|\s+(?:in))/i', $content, $traitMatches);
        if (!empty($traitMatches[1])) {
            foreach ($traitMatches[1] as $trait) {
                $result['traits'][] = trim($trait);
            }
        }

        // Tekrarları kaldır
        $result['classes'] = array_unique($result['classes']);
        $result['traits'] = array_unique($result['traits']);

        return $result;
    }
}
