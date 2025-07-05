<?php

namespace SekizliPenguen\IndexAnalyzer\Helpers;

class FileValidator
{
    /**
     * Dosyayı lintleme - syntax hatası var mı kontrol et
     *
     * @param string $filePath Dosya yolu
     * @return bool|string Başarılı ise true, hata varsa hata mesajı
     */
    public static function lintPhpFile(string $filePath): bool|string
    {
        if (!self::isValidPhpFile($filePath)) {
            return "Geçerli bir PHP dosyası değil: {$filePath}";
        }

        // exec komutu kullanılabilir mi?
        if (!function_exists('exec')) {
            return true; // Exec fonksiyonu yoksa kontrol edemiyoruz, geçerli kabul et
        }

        // PHP -l (lint) komutu ile syntax kontrolü
        $command = sprintf('php -l %s 2>&1', escapeshellarg($filePath));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            return implode("\n", $output);
        }

        return true;
    }

    /**
     * Bir PHP dosyasının geçerli olup olmadığını kontrol eder
     *
     * @param string $filePath Dosya yolu
     * @return bool Dosya geçerli mi
     */
    public static function isValidPhpFile(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        // Dosya uzantısı PHP mi?
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (strtolower($extension) !== 'php') {
            return false;
        }

        // Dil dosyalarını ve çevirileri atla
        if (strpos($filePath, '/resources/lang/') !== false ||
            strpos($filePath, '/lang/') !== false) {
            return false;
        }

        // PHP kodu geçerli mi?
        $content = file_get_contents($filePath);
        if (empty($content)) {
            return false;
        }

        // PHP açılış etiketi var mı?
        if (strpos($content, '<?php') === false) {
            return false;
        }

        return true;
    }
}
