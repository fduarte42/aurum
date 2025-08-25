<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

/**
 * MariaDB/MySQL-specific schema builder
 */
class MariaDbSchemaBuilder extends AbstractSchemaBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function createTableBuilder(string $tableName, string $operation): TableBuilderInterface
    {
        return new MariaDbTableBuilder($this->connection, $tableName, $operation);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTableExistsSQL(): string
    {
        return "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    }

    /**
     * {@inheritdoc}
     */
    protected function getIndexExistsSQL(): string
    {
        return "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?";
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropIndexSQL(string $tableName, string $indexName): string
    {
        return "DROP INDEX " . $this->quoteIdentifier($indexName) . " ON " . $this->quoteIdentifier($tableName);
    }
}
