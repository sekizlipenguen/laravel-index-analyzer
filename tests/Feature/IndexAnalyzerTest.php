<?php

namespace SekizliPenguen\IndexAnalyzer\Tests\Feature;

use Illuminate\Support\Facades\DB;
use SekizliPenguen\IndexAnalyzer\Facades\IndexAnalyzer;
use SekizliPenguen\IndexAnalyzer\Tests\TestCase;

class IndexAnalyzerTest extends TestCase
{
    /** @test */
    public function it_can_capture_queries()
    {
        // Start capturing
        IndexAnalyzer::startCapturing();

        // Execute some queries
        DB::select('SELECT * FROM test_users WHERE email = ?', ['test@example.com']);
        DB::select('SELECT * FROM test_users WHERE name = ?', ['John']);

        // Get suggestions
        $suggestions = IndexAnalyzer::generateSuggestions();

        // Assert we get suggestions
        $this->assertIsArray($suggestions);
    }

    /** @test */
    public function it_can_generate_index_statements()
    {
        // Start capturing
        IndexAnalyzer::startCapturing();

        // Execute some queries
        DB::select('SELECT * FROM test_users WHERE email = ?', ['test@example.com']);
        DB::select('SELECT * FROM test_users WHERE email = ? AND name = ?', ['test@example.com', 'John']);

        // Get SQL statements
        $statements = IndexAnalyzer::generateIndexStatements();

        // Assert we get statements
        $this->assertIsArray($statements);

        if (count($statements) > 0) {
            $this->assertStringContainsString('ALTER TABLE', $statements[0]);
            $this->assertStringContainsString('ADD INDEX', $statements[0]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test table
        DB::statement('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, created_at TIMESTAMP)');
    }

    protected function tearDown(): void
    {
        // Drop test table
        DB::statement('DROP TABLE IF EXISTS test_users');

        parent::tearDown();
    }
}
