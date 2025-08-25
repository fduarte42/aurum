<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration\Schema;

/**
 * Interface for table builder
 */
interface TableBuilderInterface
{
    /**
     * Add a column to the table
     */
    public function addColumn(string $name, string $type, array $options = []): self;

    /**
     * Add an integer column
     */
    public function integer(string $name, array $options = []): self;

    /**
     * Add a string column
     */
    public function string(string $name, array $options = []): self;

    /**
     * Add a text column
     */
    public function text(string $name, array $options = []): self;

    /**
     * Add a boolean column
     */
    public function boolean(string $name, array $options = []): self;

    /**
     * Add a decimal column
     */
    public function decimal(string $name, array $options = []): self;

    /**
     * Add a datetime column
     */
    public function datetime(string $name, array $options = []): self;

    /**
     * Add a UUID column
     */
    public function uuid(string $name, array $options = []): self;

    /**
     * Add a primary key column (auto-increment integer)
     */
    public function id(string $name = 'id'): self;

    /**
     * Add UUID primary key column
     */
    public function uuidPrimaryKey(string $name = 'id'): self;

    /**
     * Add timestamps (created_at, updated_at)
     */
    public function timestamps(): self;

    /**
     * Drop a column
     */
    public function dropColumn(string $name): self;

    /**
     * Rename a column
     */
    public function renameColumn(string $oldName, string $newName): self;

    /**
     * Change a column
     */
    public function changeColumn(string $name, string $type, array $options = []): self;

    /**
     * Add an index
     */
    public function index(array $columns, string $name = null, array $options = []): self;

    /**
     * Add a unique index
     */
    public function unique(array $columns, string $name = null): self;

    /**
     * Add a foreign key
     */
    public function foreign(array $columns, string $referencedTable, array $referencedColumns = ['id'], array $options = []): self;

    /**
     * Drop an index
     */
    public function dropIndex(string $name): self;

    /**
     * Drop a foreign key
     */
    public function dropForeign(string $name): self;

    /**
     * Execute the table creation/modification
     */
    public function create(): void;

    /**
     * Execute the table alteration
     */
    public function alter(): void;

    /**
     * Get the table name
     */
    public function getTableName(): string;
}
