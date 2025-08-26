<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli\Command;

use Fduarte42\Aurum\Cli\AbstractCommand;
use Fduarte42\Aurum\Cli\EntityResolver;
use Fduarte42\Aurum\Schema\SchemaGenerator;

/**
 * Schema generation command
 */
class SchemaCommand extends AbstractCommand
{
    private SchemaGenerator $schemaGenerator;
    private EntityResolver $entityResolver;

    protected function initializeServices(): void
    {
        parent::initializeServices();
        
        $this->schemaGenerator = new SchemaGenerator($this->metadataFactory, $this->connection);
        $this->entityResolver = new EntityResolver($this->metadataFactory);
    }

    public function getName(): string
    {
        return 'schema:generate';
    }

    public function getDescription(): string
    {
        return 'Generate database schema code from entity metadata';
    }

    public function getHelp(): string
    {
        return "🔧 Aurum Schema Generator
========================

Generates database schema code from entity metadata in multiple formats.

USAGE:
  php bin/aurum-cli.php schema generate [options]

OPTIONS:
  --entities=<list>     Comma-separated list of entity classes
  --namespace=<ns>      Generate schema for all entities in namespace
  --format=<format>     Output format: schema-builder, sql, both (default: schema-builder)
  --output=<file>       Output file path (default: output to console)
  --debug               Show detailed error information

ENTITY SELECTION:
  If neither --entities nor --namespace is provided, all registered entities will be processed.

EXAMPLES:
  # Generate SchemaBuilder code for specific entities
  php bin/aurum-cli.php schema generate --entities=\"User,Post\" --format=schema-builder

  # Generate SQL DDL for all entities in a namespace
  php bin/aurum-cli.php schema generate --namespace=\"App\\Entity\" --format=sql --output=schema.sql

  # Generate both formats for all registered entities
  php bin/aurum-cli.php schema generate --format=both

  # Auto-discover and generate schema for all entities
  php bin/aurum-cli.php schema generate

SUPPORTED FORMATS:
  schema-builder  Laravel-style fluent SchemaBuilder syntax
  sql            Raw SQL DDL statements
  both           Generate both formats

";
    }

    public function validateOptions(array $options): array
    {
        $errors = parent::validateOptions($options);
        
        $format = $options['format'] ?? 'schema-builder';
        if (!in_array($format, ['schema-builder', 'sql', 'both'])) {
            $errors[] = "Invalid format '{$format}'. Valid formats: schema-builder, sql, both";
        }
        
        return $errors;
    }

    public function execute(array $options): int
    {
        try {
            // Resolve entities
            $entityClasses = $this->entityResolver->resolveEntities($options);
            
            if (empty($entityClasses)) {
                $this->error("❌ No entities found to process");
                return 1;
            }

            $format = $options['format'] ?? 'schema-builder';
            $output = $options['output'] ?? null;

            $this->info("🔧 " . $this->entityResolver->getEntitySummary($entityClasses));
            $this->info("📊 Format: {$format}");
            echo "\n";

            // Generate schema code
            if ($format === 'schema-builder' || $format === 'both') {
                $schemaBuilderCode = $this->schemaGenerator->generateSchemaBuilderCode($entityClasses);
                $this->outputResult('SchemaBuilder Code', $schemaBuilderCode, $output, 'schema-builder.php');
            }

            if ($format === 'sql' || $format === 'both') {
                $sqlCode = $this->schemaGenerator->generateSqlDdl($entityClasses);
                $this->outputResult('SQL DDL', $sqlCode, $output, 'schema.sql');
            }

            $this->success("✅ Schema generation completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Output result to file or console
     */
    private function outputResult(string $title, string $content, ?string $outputFile, string $defaultFilename): void
    {
        if ($outputFile) {
            file_put_contents($outputFile, $content);
            $this->success("📁 {$title} saved to: {$outputFile}");
        } else {
            $this->info("\n" . str_repeat('=', 50));
            $this->info($title);
            $this->info(str_repeat('=', 50));
            echo $content . "\n";
            $this->info("💡 Use --output={$defaultFilename} to save to file");
        }
    }
}
