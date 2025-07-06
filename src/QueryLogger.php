<?php

namespace SekizliPenguen\IndexAnalyzer;

class QueryLogger
{
    protected $queries = [];

    /**
     * Sorgu kaydet
     *
     * @param string $sql
     * @param array $bindings
     * @param float $time
     * @param string $url
     * @return void
     */
    public function logQuery($sql, $bindings = [], $time = 0.0, $url = '')
    {
        $this->queries[] = [
            'sql' => $this->replaceBindings($sql, $bindings),
            'time' => $time,
            'url' => $url,
            'timestamp' => microtime(true)
        ];
    }

    /**
     * Sorgu parametrelerini değiştir
     *
     * @param string $sql
     * @param array $bindings
     * @return string
     */
    protected function replaceBindings($sql, $bindings)
    {
        if (empty($bindings)) {
            return $sql;
        }

        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    /**
     * Tüm sorguları döndür
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * Tüm sorguları temizle
     *
     * @return void
     */
    public function clearQueries()
    {
        $this->queries = [];
    }
}
