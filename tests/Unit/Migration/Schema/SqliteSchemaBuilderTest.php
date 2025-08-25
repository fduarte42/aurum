<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration\Schema;

use Fduarte42\Aurum\Connection\ConnectionFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\Schema\SqliteSchemaBuilder;
use PHPUnit\Framework\TestCase;

class SqliteSchemaBuilderTest extends TestCase
{
    private ConnectionInterface $connection;
    private SqliteSchemaBuilder $schemaBuilder;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::createSqliteConnection(':memory:');
        $this->schemaBuilder = new SqliteSchemaBuilder($this->connection);
    }

    public function testCreateTable(): void
    {
        $this->schemaBuilder->createTable('users')
            ->id()
            ->string('email', ['length' => 255, 'unique' => true])
            ->string('name')
            ->boolean('active', ['default' => true])
            ->timestamps()
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('users'));

        // Verify table structure
        $columns = $this->connection->fetchAll('PRAGMA table_info(users)');
        $columnNames = array_column($columns, 'name');

        $this->assertContains('id', $columnNames);
        $this->assertContains('email', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('active', $columnNames);
        $this->assertContains('created_at', $columnNames);
        $this->assertContains('updated_at', $columnNames);
    }

    public function testCreateTableWithUuidPrimaryKey(): void
    {
        $this->schemaBuilder->createTable('entities')
            ->uuidPrimaryKey()
            ->string('title')
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('entities'));

        $columns = $this->connection->fetchAll('PRAGMA table_info(entities)');
        $idColumn = array_filter($columns, fn($col) => $col['name'] === 'id')[0];

        $this->assertEquals('TEXT', $idColumn['type']);
        $this->assertEquals(1, $idColumn['pk']);
    }

    public function testCreateTableWithForeignKey(): void
    {
        // Create parent table first
        $this->schemaBuilder->createTable('categories')
            ->id()
            ->string('name')
            ->create();

        // Create child table with foreign key
        $this->schemaBuilder->createTable('products')
            ->id()
            ->string('name')
            ->integer('category_id')
            ->foreign(['category_id'], 'categories', ['id'])
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('products'));

        // Verify foreign key constraint exists
        $foreignKeys = $this->connection->fetchAll('PRAGMA foreign_key_list(products)');
        $this->assertCount(1, $foreignKeys);
        $this->assertEquals('categories', $foreignKeys[0]['table']);
        $this->assertEquals('category_id', $foreignKeys[0]['from']);
        $this->assertEquals('id', $foreignKeys[0]['to']);
    }

    public function testCreateTableWithIndexes(): void
    {
        $this->schemaBuilder->createTable('posts')
            ->id()
            ->string('title')
            ->string('slug')
            ->text('content')
            ->index(['title'])
            ->unique(['slug'])
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('posts'));

        // Check indexes
        $indexes = $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='posts'");
        $indexNames = array_column($indexes, 'name');

        $this->assertContains('idx_posts_title', $indexNames);
        $this->assertContains('idx_posts_slug', $indexNames);
    }

    public function testAlterTableAddColumn(): void
    {
        // Create initial table
        $this->schemaBuilder->createTable('users')
            ->id()
            ->string('email')
            ->create();

        // Add column
        $this->schemaBuilder->alterTable('users')
            ->string('phone', ['nullable' => true])
            ->alter();

        $columns = $this->connection->fetchAll('PRAGMA table_info(users)');
        $columnNames = array_column($columns, 'name');

        $this->assertContains('phone', $columnNames);
    }

    public function testDropTable(): void
    {
        $this->schemaBuilder->createTable('temp_table')
            ->id()
            ->string('name')
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('temp_table'));

        $this->schemaBuilder->dropTable('temp_table');

        $this->assertFalse($this->schemaBuilder->hasTable('temp_table'));
    }

    public function testRenameTable(): void
    {
        $this->schemaBuilder->createTable('old_name')
            ->id()
            ->string('name')
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('old_name'));

        $this->schemaBuilder->renameTable('old_name', 'new_name');

        $this->assertFalse($this->schemaBuilder->hasTable('old_name'));
        $this->assertTrue($this->schemaBuilder->hasTable('new_name'));
    }

    public function testCreateIndex(): void
    {
        $this->schemaBuilder->createTable('users')
            ->id()
            ->string('email')
            ->string('name')
            ->create();

        $this->schemaBuilder->createIndex('users', ['email'], 'idx_users_email');

        $this->assertTrue($this->schemaBuilder->hasIndex('users', 'idx_users_email'));
    }

    public function testCreateUniqueIndex(): void
    {
        $this->schemaBuilder->createTable('users')
            ->id()
            ->string('email')
            ->create();

        $this->schemaBuilder->createIndex('users', ['email'], 'idx_users_email_unique', ['unique' => true]);

        $this->assertTrue($this->schemaBuilder->hasIndex('users', 'idx_users_email_unique'));
    }

    public function testDropIndex(): void
    {
        $this->schemaBuilder->createTable('users')
            ->id()
            ->string('email')
            ->index(['email'], 'idx_users_email')
            ->create();

        $this->assertTrue($this->schemaBuilder->hasIndex('users', 'idx_users_email'));

        $this->schemaBuilder->dropIndex('users', 'idx_users_email');

        $this->assertFalse($this->schemaBuilder->hasIndex('users', 'idx_users_email'));
    }

    public function testGetPlatform(): void
    {
        $this->assertEquals('sqlite', $this->schemaBuilder->getPlatform());
    }

    public function testExecuteRawSQL(): void
    {
        $this->schemaBuilder->execute('CREATE TABLE raw_table (id INTEGER PRIMARY KEY, name TEXT)');

        $this->assertTrue($this->schemaBuilder->hasTable('raw_table'));
    }

    public function testColumnTypes(): void
    {
        $this->schemaBuilder->createTable('type_test')
            ->integer('int_col')
            ->string('string_col', ['length' => 100])
            ->text('text_col')
            ->boolean('bool_col')
            ->decimal('decimal_col', ['precision' => 10, 'scale' => 2])
            ->datetime('datetime_col')
            ->uuid('uuid_col')
            ->create();

        $columns = $this->connection->fetchAll('PRAGMA table_info(type_test)');
        $columnTypes = array_combine(
            array_column($columns, 'name'),
            array_column($columns, 'type')
        );

        $this->assertEquals('INTEGER', $columnTypes['int_col']);
        $this->assertEquals('TEXT', $columnTypes['string_col']);
        $this->assertEquals('TEXT', $columnTypes['text_col']);
        $this->assertEquals('INTEGER', $columnTypes['bool_col']);
        $this->assertEquals('REAL', $columnTypes['decimal_col']);
        $this->assertEquals('TEXT', $columnTypes['datetime_col']);
        $this->assertEquals('TEXT', $columnTypes['uuid_col']);
    }

    public function testForeignKeyConstraintsNotSupportedForExistingTables(): void
    {
        $this->schemaBuilder->createTable('users')
            ->id()
            ->string('name')
            ->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQLite does not support adding foreign key constraints to existing tables');

        $this->schemaBuilder->addForeignKey('users', ['category_id'], 'categories', ['id']);
    }

    public function testDropForeignKeyNotSupported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQLite does not support dropping foreign key constraints');

        $this->schemaBuilder->dropForeignKey('users', 'fk_users_category');
    }

    public function testComplexTableWithAllFeatures(): void
    {
        $this->schemaBuilder->createTable('complex_table')
            ->id('custom_id')
            ->string('email', ['length' => 255, 'unique' => true, 'not_null' => true])
            ->string('name', ['length' => 100, 'not_null' => true])
            ->text('description', ['nullable' => true])
            ->boolean('active', ['default' => true, 'not_null' => true])
            ->decimal('price', ['precision' => 10, 'scale' => 2, 'nullable' => true])
            ->datetime('created_at', ['not_null' => true])
            ->datetime('updated_at', ['nullable' => true])
            ->index(['name'])
            ->index(['created_at'], 'idx_created')
            ->create();

        $this->assertTrue($this->schemaBuilder->hasTable('complex_table'));
        $this->assertTrue($this->schemaBuilder->hasIndex('complex_table', 'idx_complex_table_name'));
        $this->assertTrue($this->schemaBuilder->hasIndex('complex_table', 'idx_created'));

        $columns = $this->connection->fetchAll('PRAGMA table_info(complex_table)');
        $this->assertCount(8, $columns);
    }
}
