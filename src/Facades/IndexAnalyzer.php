<?php

namespace SekizliPenguen\IndexAnalyzer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void startCapturing()
 * @method static array generateSuggestions()
 * @method static array generateIndexStatements()
 */
class IndexAnalyzer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'index-analyzer';
    }
}
