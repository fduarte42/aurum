<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Abstract base class for table builders
 */
abstract class AbstractTableBuilder implements TableBuilderInterface
{
    protected array $columns = [];
    protected array $indexes = [];
    protected array $foreignKeys = [];
    protected array $dropColumns = [];
    protected array $dropIndexes = [];
    protected array $dropForeignKeys = [];
    protected array $renameColumns = [];
    protected array $changeColumns = [];

    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly string $tableName,
        protected readonly string $operation
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn(string $name, string $type, array $options = []): self
    {
        $this->columns[$name] = [
            'type' => $type,
            'options' => $options
        ];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function integer(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'integer', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function string(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'string', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function text(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'text', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function boolean(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'boolean', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function decimal(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'decimal', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function datetime(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'datetime', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function uuid(string $name, array $options = []): self
    {
        return $this->addColumn($name, 'uuid', $options);
    }

    /**
     * {@inheritdoc}
     */
    public function id(string $name = 'id'): self
    {
        return $this->addColumn($name, 'integer', [
            'primary_key' => true,
            'auto_increment' => true,
            'not_null' => true
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function uuidPrimaryKey(string $name = 'id'): self
    {
        return $this->addColumn($name, 'uuid', [
            'primary_key' => true,
            'not_null' => true
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function timestamps(): self
    {
        $this->datetime('created_at', ['not_null' => true]);
        $this->datetime('updated_at', ['not_null' => true]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dropColumn(string $name): self
    {
        $this->dropColumns[] = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function renameColumn(string $oldName, string $newName): self
    {
        $this->renameColumns[$oldName] = $newName;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function changeColumn(string $name, string $type, array $options = []): self
    {
        $this->changeColumns[$name] = [
            'type' => $type,
            'options' => $options
        ];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function index(array $columns, string $name = null, array $options = []): self
    {
        $name = $name ?: $this->generateIndexName($columns);
        $this->indexes[$name] = [
            'columns' => $columns,
            'options' => $options
        ];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function unique(array $columns, string $name = null): self
    {
        return $this->index($columns, $name, ['unique' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function foreign(array $columns, string $referencedTable, array $referencedColumns = ['id'], array $options = []): self
    {
        $name = $options['name'] ?? $this->generateForeignKeyName($columns);
        $this->foreignKeys[$name] = [
            'columns' => $columns,
            'referenced_table' => $referencedTable,
            'referenced_columns' => $referencedColumns,
            'options' => $options
        ];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dropIndex(string $name): self
    {
        $this->dropIndexes[] = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dropForeign(string $name): self
    {
        $this->dropForeignKeys[] = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * Generate an index name
     */
    protected function generateIndexName(array $columns): string
    {
        return 'idx_' . $this->tableName . '_' . implode('_', $columns);
    }

    /**
     * Generate a foreign key constraint name
     */
    protected function generateForeignKeyName(array $columns): string
    {
        return 'fk_' . $this->tableName . '_' . implode('_', $columns);
    }

    /**
     * Quote an identifier
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * Execute SQL
     */
    protected function execute(string $sql): void
    {
        $this->connection->execute($sql);
    }

    /**
     * Build column definition SQL
     */
    abstract protected function buildColumnDefinition(string $name, array $column): string;

    /**
     * Get the SQL type for a column type
     */
    abstract protected function getSqlType(string $type, array $options): string;

    /**
     * Build CREATE TABLE SQL
     */
    abstract protected function buildCreateTableSQL(): string;

    /**
     * Build ALTER TABLE SQL
     */
    abstract protected function buildAlterTableSQL(): array;
}
