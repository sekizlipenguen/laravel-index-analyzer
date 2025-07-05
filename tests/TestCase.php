<?php

namespace SekizliPenguen\LaravelIndexAnalyzer\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SekizliPenguen\LaravelIndexAnalyzer\LaravelIndexAnalyzerServiceProvider;

class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            LaravelIndexAnalyzerServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Enable the analyzer for testing
        $app['config']->set('index-analyzer.enabled', true);
        $app['config']->set('index-analyzer.storage', 'file');
        $app['config']->set('index-analyzer.log_path', sys_get_temp_dir() . '/sql-queries-test.log');
    }
}
