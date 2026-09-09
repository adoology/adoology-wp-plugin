<?php

/**
 * Schema version gating regressions: the recorded DB version must only
 * advance once both tables provably exist, so a failed DDL keeps retrying
 * upgrades instead of marking itself done.
 */

namespace Adoology\Tests\Unit;

use Adoology\Database;
use Adoology\Tests\TestCase;
use Mockery;
use ReflectionMethod;

class DatabaseTest extends TestCase
{
    private function tables_exist($wpdb, array $tables)
    {
        $method = new ReflectionMethod(Database::class, 'tables_exist');
        $method->setAccessible(true);

        return $method->invoke(null, $wpdb, $tables);
    }

    public function test_tables_exist_returns_true_when_both_tables_present()
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static fn (string $sql, string $table): string => str_replace('%s', "'{$table}'", $sql)
        );
        $wpdb->shouldReceive('get_var')->andReturnUsing(
            static fn (string $query): ?string => str_contains($query, 'wp_adoology_events') ? 'wp_adoology_events' : 'wp_adoology_incomplete'
        );

        $this->assertTrue($this->tables_exist($wpdb, ['wp_adoology_events', 'wp_adoology_incomplete']));
    }

    public function test_tables_exist_returns_false_when_a_table_is_missing()
    {
        global $wpdb;

        $wpdb = Mockery::mock();
        $wpdb->shouldReceive('prepare')->andReturnUsing(
            static fn (string $sql, string $table): string => str_replace('%s', "'{$table}'", $sql)
        );
        $wpdb->shouldReceive('get_var')->andReturnUsing(
            static fn (string $query): ?string => str_contains($query, 'wp_adoology_events') ? 'wp_adoology_events' : null
        );

        $this->assertFalse($this->tables_exist($wpdb, ['wp_adoology_events', 'wp_adoology_incomplete']));
    }
}
