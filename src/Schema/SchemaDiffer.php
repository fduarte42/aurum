<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Schema;

use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\FieldMappingInterface;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Compares database schemas and generates migration diffs
 */
class SchemaDiffer
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory,
        private readonly SchemaIntrospector $introspector,
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Generate migration diff between current database and target entities
     */
    public function generateMigrationDiff(array $entityClasses): array
    {
        $currentSchema = $this->getCurrentSchema();
        $targetSchema = $this->getTargetSchema($entityClasses);
        
        return [
            'up' => $this->generateUpMigration($currentSchema, $targetSchema),
            'down' => $this->generateDownMigration($currentSchema, $targetSchema)
        ];
    }

    /**
     * Get current database schema
     */
    private function getCurrentSchema(): array
    {
        $schema = [];
        $tables = $this->introspector->getTables();
        
        foreach ($tables as $tableName) {
            $schema[$tableName] = $this->introspector->getTableStructure($tableName);
        }
        
        return $schema;
    }

    /**
     * Get target schema from entity metadata
     */
    private function getTargetSchema(array $entityClasses): array
    {
        $schema = [];
        
        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataFactory->getMetadataFor($entityClass);
            $tableName = $metadata->getTableName();
            
            $schema[$tableName] = [
                'name' => $tableName,
                'columns' => $this->extractColumnsFromMetadata($metadata),
                'indexes' => $this->extractIndexesFromMetadata($metadata),
                'foreign_keys' => $this->extractForeignKeysFromMetadata($metadata)
            ];
        }
        
        return $schema;
    }

    /**
     * Generate up migration (current -> target)
     */
    private function generateUpMigration(array $currentSchema, array $targetSchema): string
    {
        $migration = '';
        
        // Create new tables
        foreach ($targetSchema as $tableName => $targetTable) {
            if (!isset($currentSchema[$tableName])) {
                $migration .= $this->generateCreateTable($targetTable);
            }
        }
        
        // Modify existing tables
        foreach ($targetSchema as $tableName => $targetTable) {
            if (isset($currentSchema[$tableName])) {
                $migration .= $this->generateAlterTable($currentSchema[$tableName], $targetTable);
            }
        }
        
        // Drop removed tables
        foreach ($currentSchema as $tableName => $currentTable) {
            if (!isset($targetSchema[$tableName])) {
                $migration .= $this->generateDropTable($currentTable);
            }
        }
        
        return $migration;
    }

    /**
     * Generate down migration (target -> current)
     */
    private function generateDownMigration(array $currentSchema, array $targetSchema): string
    {
        $migration = '';
        
        // Recreate dropped tables
        foreach ($currentSchema as $tableName => $currentTable) {
            if (!isset($targetSchema[$tableName])) {
                $migration .= $this->generateCreateTable($currentTable);
            }
        }
        
        // Reverse table modifications
        foreach ($targetSchema as $tableName => $targetTable) {
            if (isset($currentSchema[$tableName])) {
                $migration .= $this->generateAlterTable($targetTable, $currentSchema[$tableName]);
            }
        }
        
        // Drop new tables
        foreach ($targetSchema as $tableName => $targetTable) {
            if (!isset($currentSchema[$tableName])) {
                $migration .= $this->generateDropTable($targetTable);
            }
        }
        
        return $migration;
    }

    /**
     * Generate create table migration code
     */
    private function generateCreateTable(array $table): string
    {
        $code = "        // Create table: {$table['name']}\n";
        $code .= "        \$this->schemaBuilder->createTable('{$table['name']}')\n";
        
        foreach ($table['columns'] as $column) {
            $code .= $this->generateColumnDefinition($column);
        }
        
        foreach ($table['indexes'] as $index) {
            $code .= $this->generateIndexDefinition($index);
        }
        
        foreach ($table['foreign_keys'] as $foreignKey) {
            $code .= $this->generateForeignKeyDefinition($foreignKey);
        }
        
        $code .= "            ->create();\n\n";
        
        return $code;
    }

    /**
     * Generate drop table migration code
     */
    private function generateDropTable(array $table): string
    {
        return "        // Drop table: {$table['name']}\n" .
               "        \$this->schemaBuilder->dropTable('{$table['name']}');\n\n";
    }

    /**
     * Generate alter table migration code
     */
    private function generateAlterTable(array $currentTable, array $targetTable): string
    {
        $code = '';
        $hasChanges = false;
        
        // Compare columns
        $columnChanges = $this->compareColumns($currentTable['columns'], $targetTable['columns']);
        if (!empty($columnChanges)) {
            if (!$hasChanges) {
                $code .= "        // Alter table: {$targetTable['name']}\n";
                $code .= "        \$this->schemaBuilder->alterTable('{$targetTable['name']}')\n";
                $hasChanges = true;
            }
            $code .= $columnChanges;
        }
        
        // Compare indexes
        $indexChanges = $this->compareIndexes($currentTable['indexes'], $targetTable['indexes']);
        if (!empty($indexChanges)) {
            if (!$hasChanges) {
                $code .= "        // Alter table: {$targetTable['name']}\n";
                $code .= "        \$this->schemaBuilder->alterTable('{$targetTable['name']}')\n";
                $hasChanges = true;
            }
            $code .= $indexChanges;
        }
        
        if ($hasChanges) {
            $code .= "            ->alter();\n\n";
        }
        
        // Handle foreign key changes separately (they often require separate statements)
        $foreignKeyChanges = $this->compareForeignKeys($currentTable['foreign_keys'], $targetTable['foreign_keys'], $targetTable['name']);
        if (!empty($foreignKeyChanges)) {
            $code .= $foreignKeyChanges;
        }
        
        return $code;
    }

    /**
     * Compare columns and generate change code
     */
    private function compareColumns(array $currentColumns, array $targetColumns): string
    {
        $code = '';
        
        // Index columns by name for easier comparison
        $currentByName = [];
        foreach ($currentColumns as $column) {
            $currentByName[$column['name']] = $column;
        }
        
        $targetByName = [];
        foreach ($targetColumns as $column) {
            $targetByName[$column['name']] = $column;
        }
        
        // Add new columns
        foreach ($targetByName as $name => $column) {
            if (!isset($currentByName[$name])) {
                $code .= $this->generateColumnDefinition($column);
            }
        }
        
        // Modify existing columns
        foreach ($targetByName as $name => $targetColumn) {
            if (isset($currentByName[$name])) {
                $currentColumn = $currentByName[$name];
                if ($this->columnsAreDifferent($currentColumn, $targetColumn)) {
                    $code .= $this->generateChangeColumnDefinition($targetColumn);
                }
            }
        }
        
        // Drop removed columns
        foreach ($currentByName as $name => $column) {
            if (!isset($targetByName[$name])) {
                $code .= "            ->dropColumn('{$name}')\n";
            }
        }
        
        return $code;
    }

    /**
     * Compare indexes and generate change code
     */
    private function compareIndexes(array $currentIndexes, array $targetIndexes): string
    {
        $code = '';
        
        // Index by name for easier comparison
        $currentByName = [];
        foreach ($currentIndexes as $index) {
            $currentByName[$index['name']] = $index;
        }
        
        $targetByName = [];
        foreach ($targetIndexes as $index) {
            $targetByName[$index['name']] = $index;
        }
        
        // Drop removed indexes
        foreach ($currentByName as $name => $index) {
            if (!isset($targetByName[$name])) {
                $code .= "            ->dropIndex('{$name}')\n";
            }
        }
        
        // Add new indexes
        foreach ($targetByName as $name => $index) {
            if (!isset($currentByName[$name])) {
                $code .= $this->generateIndexDefinition($index);
            }
        }
        
        return $code;
    }

    /**
     * Compare foreign keys and generate change code
     */
    private function compareForeignKeys(array $currentForeignKeys, array $targetForeignKeys, string $tableName): string
    {
        $code = '';
        
        // Index by name for easier comparison
        $currentByName = [];
        foreach ($currentForeignKeys as $fk) {
            $currentByName[$fk['name']] = $fk;
        }
        
        $targetByName = [];
        foreach ($targetForeignKeys as $fk) {
            $targetByName[$fk['name']] = $fk;
        }
        
        // Drop removed foreign keys
        foreach ($currentByName as $name => $fk) {
            if (!isset($targetByName[$name])) {
                $code .= "        // Drop foreign key: {$name}\n";
                $code .= "        \$this->schemaBuilder->dropForeignKey('{$tableName}', '{$name}');\n\n";
            }
        }
        
        // Add new foreign keys
        foreach ($targetByName as $name => $fk) {
            if (!isset($currentByName[$name])) {
                $code .= "        // Add foreign key: {$name}\n";
                $columns = "'" . implode("', '", $fk['columns']) . "'";
                $referencedColumns = "'" . implode("', '", $fk['referenced_columns']) . "'";
                $code .= "        \$this->schemaBuilder->addForeignKey('{$tableName}', [{$columns}], '{$fk['referenced_table']}', [{$referencedColumns}]);\n\n";
            }
        }
        
        return $code;
    }

    /**
     * Check if two columns are different
     */
    private function columnsAreDifferent(array $current, array $target): bool
    {
        $compareFields = ['type', 'nullable', 'default', 'length', 'precision', 'scale'];
        
        foreach ($compareFields as $field) {
            if (($current[$field] ?? null) !== ($target[$field] ?? null)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate column definition for migration
     */
    private function generateColumnDefinition(array $column): string
    {
        $options = [];
        
        if (isset($column['length'])) {
            $options[] = "'length' => {$column['length']}";
        }
        if (isset($column['precision'])) {
            $options[] = "'precision' => {$column['precision']}";
        }
        if (isset($column['scale'])) {
            $options[] = "'scale' => {$column['scale']}";
        }
        if ($column['nullable'] ?? false) {
            $options[] = "'nullable' => true";
        } else {
            $options[] = "'not_null' => true";
        }
        if (isset($column['default'])) {
            $defaultValue = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
            $options[] = "'default' => {$defaultValue}";
        }
        
        $optionsStr = empty($options) ? '' : ', [' . implode(', ', $options) . ']';
        
        // Handle special column types
        if ($column['primary_key'] ?? false) {
            if ($column['type'] === 'uuid') {
                return "            ->uuidPrimaryKey('{$column['name']}')\n";
            } else {
                return "            ->id('{$column['name']}')\n";
            }
        }
        
        $method = match ($column['type']) {
            'string' => 'string',
            'text' => 'text',
            'integer' => 'integer',
            'float' => 'float',
            'boolean' => 'boolean',
            'decimal' => 'decimal',
            'datetime' => 'datetime',
            'uuid' => 'uuid',
            default => 'string'
        };
        
        return "            ->{$method}('{$column['name']}'{$optionsStr})\n";
    }

    /**
     * Generate change column definition for migration
     */
    private function generateChangeColumnDefinition(array $column): string
    {
        $options = [];
        
        if (isset($column['length'])) {
            $options[] = "'length' => {$column['length']}";
        }
        if (isset($column['precision'])) {
            $options[] = "'precision' => {$column['precision']}";
        }
        if (isset($column['scale'])) {
            $options[] = "'scale' => {$column['scale']}";
        }
        if ($column['nullable'] ?? false) {
            $options[] = "'nullable' => true";
        } else {
            $options[] = "'not_null' => true";
        }
        if (isset($column['default'])) {
            $defaultValue = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
            $options[] = "'default' => {$defaultValue}";
        }
        
        $optionsStr = empty($options) ? '' : ', [' . implode(', ', $options) . ']';
        
        return "            ->changeColumn('{$column['name']}', '{$column['type']}'{$optionsStr})\n";
    }

    /**
     * Generate index definition for migration
     */
    private function generateIndexDefinition(array $index): string
    {
        $columns = "'" . implode("', '", $index['columns']) . "'";
        $name = isset($index['name']) ? ", '{$index['name']}'" : '';
        
        if ($index['unique']) {
            return "            ->unique([{$columns}]{$name})\n";
        } else {
            return "            ->index([{$columns}]{$name})\n";
        }
    }

    /**
     * Generate foreign key definition for migration
     */
    private function generateForeignKeyDefinition(array $foreignKey): string
    {
        $columns = "'" . implode("', '", $foreignKey['columns']) . "'";
        $referencedColumns = "'" . implode("', '", $foreignKey['referenced_columns']) . "'";
        
        return "            ->foreign([{$columns}], '{$foreignKey['referenced_table']}', [{$referencedColumns}])\n";
    }

    /**
     * Extract columns from entity metadata
     */
    private function extractColumnsFromMetadata($metadata): array
    {
        $columns = [];

        foreach ($metadata->getFieldMappings() as $fieldName => $mapping) {
            $columns[] = [
                'name' => $mapping->getColumnName(),
                'type' => $mapping->getType(),
                'nullable' => $mapping->isNullable(),
                'default' => $mapping->getDefault(),
                'primary_key' => $mapping->isIdentifier(),
                'auto_increment' => $mapping->isIdentifier() && $mapping->getType() === 'integer',
                'length' => $mapping->getLength(),
                'precision' => $mapping->getPrecision(),
                'scale' => $mapping->getScale()
            ];
        }

        return $columns;
    }

    /**
     * Extract indexes from entity metadata
     */
    private function extractIndexesFromMetadata($metadata): array
    {
        $indexes = [];

        // Extract unique constraints
        foreach ($metadata->getFieldMappings() as $fieldName => $mapping) {
            if ($mapping->isUnique()) {
                $columnName = $mapping->getColumnName();
                $indexes[] = [
                    'name' => 'idx_' . $metadata->getTableName() . '_' . $columnName . '_unique',
                    'columns' => [$columnName],
                    'unique' => true
                ];
            }
        }

        return $indexes;
    }

    /**
     * Extract foreign keys from entity metadata
     */
    private function extractForeignKeysFromMetadata($metadata): array
    {
        $foreignKeys = [];
        
        // This would need to be implemented based on how associations are stored in metadata
        // For now, return empty array
        
        return $foreignKeys;
    }
}
