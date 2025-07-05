<?php

namespace SekizliPenguen\IndexAnalyzer\Helpers;

use Illuminate\Support\Facades\File;

class ModelFinder
{
    /**
     * Önbelleğe alınmış model sınıfları
     *
     * @var array
     */
    protected static $models = [];

    /**
     * Önbelleğe alınmış trait'ler
     *
     * @var array
     */
    protected static $traits = [];

    /**
     * Tüm import ifadelerini oluşturur (model + trait)
     *
     * @return array
     */
    public static function getAllImports(): array
    {
        return array_merge(
            static::getModelImports(),
            static::getTraitImports()
        );
    }

    /**
     * Model sınıfları için use ifadelerini oluşturur
     *
     * @return array
     */
    public static function getModelImports(): array
    {
        static::cacheAllModels();
        $imports = [];

        foreach (static::$models as $shortName => $fullName) {
            $imports[] = "use {$fullName};";
        }

        return $imports;
    }

    /**
     * Tüm modelleri önbelleğe alır
     *
     * @return void
     */
    public static function cacheAllModels(): void
    {
        if (!empty(static::$models)) {
            return; // Zaten önbelleğe alınmış
        }

        // Laravel Model path'lerini kontrol et
        $modelPaths = config('index-analyzer.model_paths', ['app/Models']);
        $basePath = base_path();

        // Tüm model dizinlerini tara
        foreach ($modelPaths as $path) {
            $fullPath = $basePath . '/' . ltrim($path, '/');

            if (!File::isDirectory($fullPath)) {
                continue;
            }

            // Tüm PHP dosyalarını al
            $files = File::allFiles($fullPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());

                // Sınıf adını bul
                preg_match('/class\s+([\w]+)/i', $content, $classMatches);

                if (empty($classMatches[1])) {
                    continue;
                }

                $className = $classMatches[1];

                // Namespace'i bul
                preg_match('/namespace\s+([^;]+);/i', $content, $nsMatches);
                $namespace = $nsMatches[1] ?? null;

                if ($namespace) {
                    $fullClassName = $namespace . '\\' . $className;
                    static::$models[$className] = $fullClassName;
                } else {
                    static::$models[$className] = $className;
                }
            }
        }

        // Önemli Laravel model sınıflarını ekle
        static::addCommonLaravelModels();
    }

    /**
     * Yaygın Laravel modellerini ekler
     *
     * @return void
     */
    protected static function addCommonLaravelModels(): void
    {
        // Yaygın Laravel modelleri
        $commonModels = [
            'User' => 'App\\Models\\User',
            'Model' => 'Illuminate\\Database\\Eloquent\\Model',
            'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
            'EloquentBuilder' => 'Illuminate\\Database\\Eloquent\\Builder',
            'QueryBuilder' => 'Spatie\\QueryBuilder\\QueryBuilder',
        ];

        foreach ($commonModels as $name => $class) {
            if (!isset(static::$models[$name])) {
                static::$models[$name] = $class;
            }
        }

        // Hata aldığımız modelleri ekleyelim
        $errorModels = [
            'Blog' => 'App\\Models\\Blog',
            'Category' => 'App\\Models\\Category',
            'Order' => 'App\\Models\\Order',
            'OrderReturnRequest' => 'App\\Models\\OrderReturnRequest',
            'UserFavorite' => 'App\\Models\\UserFavorite',
            'Products' => 'App\\Models\\Products',
            'Producer' => 'App\\Models\\Producer',
            'ProductImage' => 'App\\Models\\ProductImage',
            'ProductCategory' => 'App\\Models\\ProductCategory',
            'OrderProduct' => 'App\\Models\\OrderProduct',
            'EventCart' => 'App\\Models\\EventCart',
            'BinCode' => 'App\\Models\\BinCode',
            'User' => 'App\\Models\\User',
            'Carbon' => 'Carbon\\Carbon',
            'ProductCache' => 'App\\Traits\\Cache\\ProductCache', // App\Traits\ProductCache yerine doğru yol belirtildi
            'AlexaCRM\\WebAPI\\ClientFactory' => 'AlexaCRM\\WebAPI\\ClientFactory',
        ];

        foreach ($errorModels as $model => $namespace) {
            if (!isset(static::$models[$model])) {
                static::$models[$model] = $namespace;
            }
        }

        // Yaygın çakışan model ve trait'ler
        $conflictModels = [
            'Coupon' => [
                'App\\Models\\Coupon\\Coupon',
                'App\\Traits\\Basket\\Coupon'
            ],
            'Cache' => [
                'App\\Traits\\Cache\\Cache',
                'Illuminate\\Support\\Facades\\Cache'
            ],
            'ShippingMethod' => [
                'App\\Models\\PaymentDelivery\\ShippingMethod\\ShippingMethod',
                'App\\Traits\\PaymentDelivery\\ShippingMethod'
            ]
        ];

        // Çakışan modeller için alias ekleyelim
        foreach ($conflictModels as $shortName => $namespaces) {
            foreach ($namespaces as $index => $namespace) {
                $parts = explode('\\', $namespace);
                $prefix = count($parts) > 2 ? $parts[count($parts) - 2] : 'Alias';

                // İlk olanı normal ekle, diğerlerini alias'lı olarak ekle
                if ($index === 0) {
                    static::$models[$shortName] = $namespace;
                } else {
                    static::$models[$prefix . $shortName] = $namespace;
                }
            }
        }
    }

    /**
     * Trait'ler için use ifadelerini oluşturur
     *
     * @return array
     */
    public static function getTraitImports(): array
    {
        static::cacheAllTraits();
        $imports = [];

        foreach (static::$traits as $shortName => $fullName) {
            $imports[] = "use {$fullName};";
        }

        return $imports;
    }

    /**
     * Tüm trait'leri önbelleğe alır
     *
     * @return void
     */
    public static function cacheAllTraits(): void
    {
        if (!empty(static::$traits)) {
            return; // Zaten önbelleğe alınmış
        }

        // Trait'leri projede ara
        $basePath = base_path();
        $appPath = $basePath . '/app';

        if (File::isDirectory($appPath)) {
            // App klasöründeki tüm PHP dosyalarını tara
            $files = File::allFiles($appPath);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());

                // Trait adını bul
                preg_match('/trait\s+([\w]+)/i', $content, $traitMatches);

                if (empty($traitMatches[1])) {
                    continue;
                }

                $traitName = $traitMatches[1];

                // Namespace'i bul
                preg_match('/namespace\s+([^;]+);/i', $content, $nsMatches);
                $namespace = $nsMatches[1] ?? null;

                if ($namespace) {
                    $fullTraitName = $namespace . '\\' . $traitName;
                    static::$traits[$traitName] = $fullTraitName;
                } else {
                    static::$traits[$traitName] = $traitName;
                }
            }
        }

        // Yaygın trait'leri ekle
        static::addCommonTraits();
    }

    /**
     * Yaygın trait'leri ekler
     *
     * @return void
     */
    protected static function addCommonTraits(): void
    {
        // Yaygın trait'ler
        $commonTraits = [
            'HasFactory' => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
            'SoftDeletes' => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
            'ProductCache' => 'App\\Traits\\ProductCache',
        ];

        foreach ($commonTraits as $name => $trait) {
            if (!isset(static::$traits[$name])) {
                static::$traits[$name] = $trait;
            }
        }
    }

    /**
     * Bir model sınıfını uygun namespace ile döndürür
     *
     * @param string $modelName Kısa model adı
     * @return string|null Tam namespace'li model adı
     */
    public static function getModelClass(string $modelName): ?string
    {
        static::cacheAllModels();
        return static::$models[$modelName] ?? null;
    }

    /**
     * Bir trait'i uygun namespace ile döndürür
     *
     * @param string $traitName Kısa trait adı
     * @return string|null Tam namespace'li trait adı
     */
    public static function getTraitClass(string $traitName): ?string
    {
        static::cacheAllTraits();
        return static::$traits[$traitName] ?? null;
    }

    /**
     * Model eşleştirmesini manuel olarak ekler
     *
     * @param string $shortName Kısa model adı
     * @param string $fullName Tam namespace'li model adı
     * @return void
     */
    public static function addModelMapping(string $shortName, string $fullName): void
    {
        static::cacheAllModels();
        static::$models[$shortName] = $fullName;
    }

    /**
     * Trait eşleştirmesini manuel olarak ekler
     *
     * @param string $shortName Kısa trait adı
     * @param string $fullName Tam namespace'li trait adı
     * @return void
     */
    public static function addTraitMapping(string $shortName, string $fullName): void
    {
        static::cacheAllTraits();
        static::$traits[$shortName] = $fullName;
    }
}
