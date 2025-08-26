<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Schema;

use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Metadata\FieldMappingInterface;
use Fduarte42\Aurum\Metadata\AssociationMappingInterface;
use Fduarte42\Aurum\Connection\ConnectionInterface;
use Fduarte42\Aurum\Migration\Schema\SchemaBuilderFactory;

/**
 * Generates database schema code from entity metadata
 */
class SchemaGenerator
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory,
        private readonly ConnectionInterface $connection
    ) {
    }

    /**
     * Generate SchemaBuilder code for all entities
     */
    public function generateSchemaBuilderCode(array $entityClasses): string
    {
        $code = "<?php\n\n";
        $code .= "// Generated SchemaBuilder code\n";
        $code .= "// This code can be used in migrations or for schema setup\n\n";
        $code .= "use Fduarte42\\Aurum\\Migration\\Schema\\SchemaBuilderInterface;\n\n";
        $code .= "function createSchema(SchemaBuilderInterface \$schemaBuilder): void\n{\n";

        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataFactory->getMetadataFor($entityClass);
            $code .= $this->generateTableSchemaBuilder($metadata);
            $code .= "\n";
        }

        $code .= "}\n";
        return $code;
    }

    /**
     * Generate SQL DDL statements for all entities
     */
    public function generateSqlDdl(array $entityClasses): string
    {
        $schemaBuilder = SchemaBuilderFactory::create($this->connection);
        $platform = $this->connection->getPlatform();
        
        $sql = "-- Generated SQL DDL for {$platform}\n";
        $sql .= "-- This SQL can be executed directly on the database\n\n";

        foreach ($entityClasses as $entityClass) {
            $metadata = $this->metadataFactory->getMetadataFor($entityClass);
            $sql .= $this->generateTableSql($metadata, $schemaBuilder);
            $sql .= "\n";
        }

        return $sql;
    }

    /**
     * Generate SchemaBuilder code for a single table
     */
    private function generateTableSchemaBuilder($metadata): string
    {
        $tableName = $metadata->getTableName();
        $code = "    // Table: {$tableName}\n";
        $code .= "    \$schemaBuilder->createTable('{$tableName}')\n";

        // Add columns
        foreach ($metadata->getFieldMappings() as $fieldName => $mapping) {
            $code .= $this->generateColumnSchemaBuilder($mapping);
        }

        // Add indexes
        $indexes = $this->extractIndexes($metadata);
        foreach ($indexes as $index) {
            $code .= $this->generateIndexSchemaBuilder($index);
        }

        // Add foreign keys
        $foreignKeys = $this->extractForeignKeys($metadata);
        foreach ($foreignKeys as $foreignKey) {
            $code .= $this->generateForeignKeySchemaBuilder($foreignKey);
        }

        $code .= "        ->create();\n";
        return $code;
    }

    /**
     * Generate SQL DDL for a single table
     */
    private function generateTableSql($metadata, $schemaBuilder): string
    {
        $tableName = $metadata->getTableName();
        $platform = $this->connection->getPlatform();
        
        $sql = "-- Table: {$tableName}\n";
        
        if ($platform === 'sqlite') {
            $sql .= $this->generateSqliteTableSql($metadata);
        } else {
            $sql .= $this->generateMariaDbTableSql($metadata);
        }

        return $sql;
    }

    /**
     * Generate column definition for SchemaBuilder
     */
    private function generateColumnSchemaBuilder(FieldMappingInterface $mapping): string
    {
        $columnName = $mapping->getColumnName();
        $type = $mapping->getType();
        $options = [];

        // Handle column options
        if ($mapping->getLength() !== null) {
            $options[] = "'length' => {$mapping->getLength()}";
        }
        if ($mapping->getPrecision() !== null) {
            $options[] = "'precision' => {$mapping->getPrecision()}";
        }
        if ($mapping->getScale() !== null) {
            $options[] = "'scale' => {$mapping->getScale()}";
        }
        if ($mapping->isNullable()) {
            $options[] = "'nullable' => true";
        } else {
            $options[] = "'not_null' => true";
        }
        if ($mapping->getDefault() !== null) {
            $defaultValue = $mapping->getDefault();
            if (is_string($defaultValue)) {
                $defaultValue = "'{$defaultValue}'";
            } elseif (is_bool($defaultValue)) {
                $defaultValue = $defaultValue ? 'true' : 'false';
            }
            $options[] = "'default' => {$defaultValue}";
        }
        // Note: unique constraints are handled as separate indexes, not column options

        $optionsStr = empty($options) ? '' : ', [' . implode(', ', $options) . ']';

        // Handle special column types
        if ($mapping->isIdentifier()) {
            if ($type === 'uuid') {
                return "        ->uuidPrimaryKey('{$columnName}')\n";
            } else {
                return "        ->id('{$columnName}')\n";
            }
        }

        // Map types to SchemaBuilder methods
        $method = match ($type) {
            'string' => 'string',
            'text' => 'text',
            'integer' => 'integer',
            'float' => 'float',
            'boolean' => 'boolean',
            'decimal', 'decimal_ext', 'decimal_string' => 'decimal',
            'datetime', 'date', 'time', 'datetime_tz' => 'datetime',
            'uuid' => 'uuid',
            'json' => 'text', // JSON stored as text
            default => 'string'
        };

        return "        ->{$method}('{$columnName}'{$optionsStr})\n";
    }

    /**
     * Generate index definition for SchemaBuilder
     */
    private function generateIndexSchemaBuilder(array $index): string
    {
        $columns = "'" . implode("', '", $index['columns']) . "'";
        $name = isset($index['name']) ? ", '{$index['name']}'" : '';

        if ($index['unique']) {
            return "        ->unique([{$columns}]{$name})\n";
        } else {
            return "        ->index([{$columns}]{$name})\n";
        }
    }

    /**
     * Generate foreign key definition for SchemaBuilder
     */
    private function generateForeignKeySchemaBuilder(array $foreignKey): string
    {
        $columns = "'" . implode("', '", $foreignKey['columns']) . "'";
        $referencedTable = $foreignKey['referenced_table'];
        $referencedColumns = "'" . implode("', '", $foreignKey['referenced_columns']) . "'";

        return "        ->foreign([{$columns}], '{$referencedTable}', [{$referencedColumns}])\n";
    }

    /**
     * Extract foreign keys from metadata
     */
    private function extractForeignKeys($metadata): array
    {
        $foreignKeys = [];

        // Extract foreign keys from association mappings
        foreach ($metadata->getAssociationMappings() as $fieldName => $mapping) {
            // Only process owning side associations that have join columns
            if ($mapping->isOwningSide() && $mapping->getJoinColumn() !== null) {
                $targetTableName = $this->getTableNameFromEntityClass($mapping->getTargetEntity());

                $foreignKeys[] = [
                    'columns' => [$mapping->getJoinColumn()],
                    'referenced_table' => $targetTableName,
                    'referenced_columns' => [$mapping->getReferencedColumnName() ?? 'id']
                ];
            }
        }

        return $foreignKeys;
    }

    /**
     * Get table name from entity class name
     */
    private function getTableNameFromEntityClass(string $entityClass): string
    {
        // Try to get metadata for the target entity to get its table name
        try {
            $targetMetadata = $this->metadataFactory->getMetadataFor($entityClass);
            return $targetMetadata->getTableName();
        } catch (\Exception $e) {
            // Fallback: convert class name to table name
            $shortName = (new \ReflectionClass($entityClass))->getShortName();
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $shortName));
        }
    }

    /**
     * Extract indexes from metadata
     */
    private function extractIndexes($metadata): array
    {
        $indexes = [];

        // Extract unique constraints
        foreach ($metadata->getFieldMappings() as $fieldName => $mapping) {
            if ($mapping->isUnique()) {
                $columnName = $mapping->getColumnName();
                $indexes[] = [
                    'columns' => [$columnName],
                    'unique' => true,
                    'name' => 'idx_' . $metadata->getTableName() . '_' . $columnName . '_unique'
                ];
            }
        }

        // Extract indexes from entity metadata if available
        if (method_exists($metadata, 'getIndexes')) {
            foreach ($metadata->getIndexes() as $index) {
                $indexes[] = [
                    'columns' => $index['columns'],
                    'unique' => $index['unique'] ?? false,
                    'name' => $index['name'] ?? null
                ];
            }
        }

        return $indexes;
    }

    /**
     * Generate SQLite table SQL
     */
    private function generateSqliteTableSql($metadata): string
    {
        $tableName = $metadata->getTableName();
        $sql = "CREATE TABLE \"{$tableName}\" (\n";
        
        $columnDefinitions = [];
        $primaryKeys = [];

        foreach ($metadata->getFieldMappings() as $fieldName => $mapping) {
            $columnName = $mapping->getColumnName();
            $columnDefinitions[] = "    " . $this->generateSqliteColumnDefinition($mapping);

            if ($mapping->isIdentifier()) {
                $primaryKeys[] = $columnName;
            }
        }

        $sql .= implode(",\n", $columnDefinitions);
        
        if (count($primaryKeys) > 1) {
            $sql .= ",\n    PRIMARY KEY (\"" . implode('", "', $primaryKeys) . "\")";
        }
        
        $sql .= "\n);\n";

        // Add indexes
        $indexes = $this->extractIndexes($metadata);
        foreach ($indexes as $index) {
            $sql .= $this->generateSqliteIndexSql($tableName, $index);
        }

        // Add foreign keys (SQLite foreign keys are added in table definition or via PRAGMA)
        $foreignKeys = $this->extractForeignKeys($metadata);
        if (!empty($foreignKeys)) {
            $sql .= "\n-- Enable foreign key constraints\n";
            $sql .= "PRAGMA foreign_keys = ON;\n";
        }

        return $sql;
    }

    /**
     * Generate MariaDB table SQL
     */
    private function generateMariaDbTableSql($metadata): string
    {
        $tableName = $metadata->getTableName();
        $sql = "CREATE TABLE `{$tableName}` (\n";
        
        $columnDefinitions = [];
        $primaryKeys = [];

        foreach ($metadata->getFieldMappings() as $fieldName => $mapping) {
            $columnName = $mapping->getColumnName();
            $columnDefinitions[] = "    " . $this->generateMariaDbColumnDefinition($mapping);

            if ($mapping->isIdentifier()) {
                $primaryKeys[] = $columnName;
            }
        }

        $sql .= implode(",\n", $columnDefinitions);
        
        if (count($primaryKeys) > 1) {
            $sql .= ",\n    PRIMARY KEY (`" . implode('`, `', $primaryKeys) . "`)";
        }
        
        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";

        // Add indexes
        $indexes = $this->extractIndexes($metadata);
        foreach ($indexes as $index) {
            $sql .= $this->generateMariaDbIndexSql($tableName, $index);
        }

        // Add foreign keys
        $foreignKeys = $this->extractForeignKeys($metadata);
        foreach ($foreignKeys as $foreignKey) {
            $sql .= $this->generateMariaDbForeignKeySql($tableName, $foreignKey);
        }

        return $sql;
    }

    /**
     * Generate SQLite column definition
     */
    private function generateSqliteColumnDefinition(FieldMappingInterface $mapping): string
    {
        $columnName = $mapping->getColumnName();
        $type = $this->mapTypeToSqlite($mapping->getType(), $mapping);
        $definition = "\"{$columnName}\" {$type}";

        if (!$mapping->isNullable()) {
            $definition .= " NOT NULL";
        }

        if ($mapping->isIdentifier()) {
            if ($mapping->getType() === 'integer') {
                $definition .= " PRIMARY KEY AUTOINCREMENT";
            } else {
                $definition .= " PRIMARY KEY";
            }
        }

        if ($mapping->getDefault() !== null) {
            $defaultValue = $mapping->getDefault();
            if (is_string($defaultValue)) {
                $defaultValue = "'{$defaultValue}'";
            } elseif (is_bool($defaultValue)) {
                $defaultValue = $defaultValue ? '1' : '0';
            }
            $definition .= " DEFAULT {$defaultValue}";
        }

        return $definition;
    }

    /**
     * Generate MariaDB column definition
     */
    private function generateMariaDbColumnDefinition(FieldMappingInterface $mapping): string
    {
        $columnName = $mapping->getColumnName();
        $type = $this->mapTypeToMariaDb($mapping->getType(), $mapping);
        $definition = "`{$columnName}` {$type}";

        if (!$mapping->isNullable()) {
            $definition .= " NOT NULL";
        }

        if ($mapping->isIdentifier() && $mapping->getType() === 'integer') {
            $definition .= " AUTO_INCREMENT PRIMARY KEY";
        } elseif ($mapping->isIdentifier()) {
            $definition .= " PRIMARY KEY";
        }

        if ($mapping->getDefault() !== null) {
            $defaultValue = $mapping->getDefault();
            if (is_string($defaultValue)) {
                $defaultValue = "'{$defaultValue}'";
            } elseif (is_bool($defaultValue)) {
                $defaultValue = $defaultValue ? '1' : '0';
            }
            $definition .= " DEFAULT {$defaultValue}";
        }

        return $definition;
    }

    /**
     * Map type to SQLite SQL type
     */
    private function mapTypeToSqlite(string $type, FieldMappingInterface $mapping): string
    {
        return match ($type) {
            'string' => 'TEXT',
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'float' => 'REAL',
            'boolean' => 'INTEGER',
            'decimal', 'decimal_ext', 'decimal_string' => 'TEXT',
            'datetime', 'date', 'time', 'datetime_tz' => 'TEXT',
            'uuid' => 'TEXT',
            'json' => 'TEXT',
            default => 'TEXT'
        };
    }

    /**
     * Map type to MariaDB SQL type
     */
    private function mapTypeToMariaDb(string $type, FieldMappingInterface $mapping): string
    {
        return match ($type) {
            'string' => 'VARCHAR(' . ($mapping->getLength() ?? 255) . ')',
            'text' => 'TEXT',
            'integer' => 'INT',
            'float' => 'DOUBLE',
            'boolean' => 'TINYINT(1)',
            'decimal', 'decimal_ext', 'decimal_string' => 'DECIMAL(' . ($mapping->getPrecision() ?? 10) . ',' . ($mapping->getScale() ?? 2) . ')',
            'datetime' => 'DATETIME',
            'date' => 'DATE',
            'time' => 'TIME',
            'datetime_tz' => 'JSON',
            'uuid' => 'CHAR(36)',
            'json' => 'JSON',
            default => 'VARCHAR(255)'
        };
    }

    /**
     * Generate SQLite index SQL
     */
    private function generateSqliteIndexSql(string $tableName, array $index): string
    {
        $indexName = $index['name'] ?? 'idx_' . $tableName . '_' . implode('_', $index['columns']);
        $unique = $index['unique'] ? 'UNIQUE ' : '';
        $columns = '"' . implode('", "', $index['columns']) . '"';

        return "CREATE {$unique}INDEX \"{$indexName}\" ON \"{$tableName}\" ({$columns});\n";
    }

    /**
     * Generate MariaDB index SQL
     */
    private function generateMariaDbIndexSql(string $tableName, array $index): string
    {
        $indexName = $index['name'] ?? 'idx_' . $tableName . '_' . implode('_', $index['columns']);
        $unique = $index['unique'] ? 'UNIQUE ' : '';
        $columns = '`' . implode('`, `', $index['columns']) . '`';

        return "CREATE {$unique}INDEX `{$indexName}` ON `{$tableName}` ({$columns});\n";
    }

    /**
     * Generate MariaDB foreign key SQL
     */
    private function generateMariaDbForeignKeySql(string $tableName, array $foreignKey): string
    {
        $constraintName = 'fk_' . $tableName . '_' . implode('_', $foreignKey['columns']);
        $columns = '`' . implode('`, `', $foreignKey['columns']) . '`';
        $referencedTable = $foreignKey['referenced_table'];
        $referencedColumns = '`' . implode('`, `', $foreignKey['referenced_columns']) . '`';

        return "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$constraintName}` " .
               "FOREIGN KEY ({$columns}) REFERENCES `{$referencedTable}` ({$referencedColumns});\n";
    }
}
