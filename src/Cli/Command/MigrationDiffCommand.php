<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli\Command;

use Fduarte42\Aurum\Cli\AbstractCommand;
use Fduarte42\Aurum\Cli\EntityResolver;
use Fduarte42\Aurum\Schema\SchemaDiffer;
use Fduarte42\Aurum\Schema\SchemaIntrospector;
use Fduarte42\Aurum\Migration\MigrationService;

/**
 * Migration diff command
 */
class MigrationDiffCommand extends AbstractCommand
{
    private SchemaDiffer $schemaDiffer;
    private EntityResolver $entityResolver;
    private MigrationService $migrationService;

    protected function initializeServices(): void
    {
        parent::initializeServices();
        
        $introspector = new SchemaIntrospector($this->connection);
        $this->schemaDiffer = new SchemaDiffer($this->metadataFactory, $introspector, $this->connection);
        $this->entityResolver = new EntityResolver($this->metadataFactory);
        
        // Get migration service from container
        $container = \Fduarte42\Aurum\DependencyInjection\ContainerBuilder::createORM($this->config);
        $this->migrationService = $container->get(MigrationService::class);
    }

    public function getName(): string
    {
        return 'migration:diff';
    }

    public function getDescription(): string
    {
        return 'Compare database schema and generate migration diff';
    }

    public function getHelp(): string
    {
        return "🔧 Aurum Migration Diff Generator
=================================

Compares current database schema with target schema from entities
and generates migration code with up/down methods.

USAGE:
  php bin/aurum-cli.php migration diff [options]

OPTIONS:
  --entities=<list>     Comma-separated list of entity classes
  --namespace=<ns>      Generate diff for all entities in namespace
  --name=<name>         Migration name (generates migration file)
  --output=<file>       Output file path (custom migration file)
  --preview             Preview migration diff without creating files
  --debug               Show detailed error information

ENTITY SELECTION:
  If neither --entities nor --namespace is provided, all registered entities will be processed.

EXAMPLES:
  # Preview migration diff for specific entities
  php bin/aurum-cli.php migration diff --entities=\"User,Post\" --preview

  # Generate migration file for entities in namespace
  php bin/aurum-cli.php migration diff --namespace=\"App\\Entity\" --name=\"UpdateUserSchema\"

  # Generate migration for all entities and save to custom file
  php bin/aurum-cli.php migration diff --output=my-migration.php

  # Auto-discover all entities and preview changes
  php bin/aurum-cli.php migration diff --preview

  # Generate migration file for all registered entities
  php bin/aurum-cli.php migration diff --name=\"UpdateAllEntities\"

MIGRATION MODES:
  --preview             Show migration diff without creating files
  --name=<name>         Generate official migration file with version
  --output=<file>       Save to custom file location

";
    }

    public function validateOptions(array $options): array
    {
        $errors = parent::validateOptions($options);
        
        // Check for conflicting output options
        $hasName = !empty($options['name']);
        $hasOutput = !empty($options['output']);
        $hasPreview = !empty($options['preview']);
        
        if (($hasName && $hasOutput) || ($hasName && $hasPreview) || ($hasOutput && $hasPreview)) {
            $errors[] = "Cannot specify multiple output options (--name, --output, --preview)";
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

            $this->info("🔧 " . $this->entityResolver->getEntitySummary($entityClasses));
            echo "\n";

            // Generate migration diff
            $diff = $this->schemaDiffer->generateMigrationDiff($entityClasses);
            
            if (empty(trim($diff['up'])) && empty(trim($diff['down']))) {
                $this->success("✅ No schema changes detected - database is up to date!");
                return 0;
            }

            // Handle different output modes
            if (isset($options['preview']) || (!isset($options['name']) && !isset($options['output']))) {
                $this->outputMigrationCode($diff);
            } elseif (isset($options['name'])) {
                $this->generateMigrationFile($diff, $options['name']);
            } elseif (isset($options['output'])) {
                $this->saveMigrationToFile($options['output'], $diff, $options['name'] ?? 'SchemaDiff');
            }

            $this->success("✅ Migration diff completed!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate official migration file
     */
    private function generateMigrationFile(array $diff, string $migrationName): void
    {
        $version = $this->migrationService->generate($migrationName);
        $this->updateMigrationFile($version, $diff, $migrationName);
        $this->success("✅ Migration generated: Version{$version}");
    }

    /**
     * Update generated migration file with diff content
     */
    private function updateMigrationFile(string $version, array $diff, string $description): void
    {
        $migrationsDir = $this->config['migrations']['directory'] ?? 'migrations';
        $filePath = $migrationsDir . "/Version{$version}.php";
        
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Migration file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        
        // Replace the up method
        $upMethod = "    public function up(ConnectionInterface \$connection): void\n    {\n{$diff['up']}    }";
        $content = preg_replace('/public function up\(ConnectionInterface \$connection\): void\s*\{[^}]*\}/', $upMethod, $content);
        
        // Replace the down method
        $downMethod = "    public function down(ConnectionInterface \$connection): void\n    {\n{$diff['down']}    }";
        $content = preg_replace('/public function down\(ConnectionInterface \$connection\): void\s*\{[^}]*\}/', $downMethod, $content);
        
        file_put_contents($filePath, $content);
    }

    /**
     * Save migration to custom file
     */
    private function saveMigrationToFile(string $filePath, array $diff, string $description): void
    {
        $version = date('YmdHis');
        $className = 'Version' . $version;
        $namespace = $this->config['migrations']['namespace'] ?? 'Migrations';
        
        $content = $this->generateMigrationFileContent($className, $namespace, $description, $diff);
        file_put_contents($filePath, $content);
        $this->success("✅ Migration saved to: {$filePath}");
    }

    /**
     * Output migration code to console
     */
    private function outputMigrationCode(array $diff): void
    {
        $this->info(str_repeat('=', 60));
        $this->info("UP MIGRATION (Current -> Target)");
        $this->info(str_repeat('=', 60));
        
        if (!empty(trim($diff['up']))) {
            echo "    public function up(ConnectionInterface \$connection): void\n";
            echo "    {\n";
            echo $diff['up'];
            echo "    }\n";
        } else {
            echo "    // No changes needed\n";
        }
        
        $this->info("\n" . str_repeat('=', 60));
        $this->info("DOWN MIGRATION (Target -> Current)");
        $this->info(str_repeat('=', 60));
        
        if (!empty(trim($diff['down']))) {
            echo "    public function down(ConnectionInterface \$connection): void\n";
            echo "    {\n";
            echo $diff['down'];
            echo "    }\n";
        } else {
            echo "    // No changes needed\n";
        }
        
        echo "\n";
        $this->info("💡 Use --name=\"MigrationName\" to generate a migration file");
        $this->info("💡 Use --output=migration.php to save to a custom file");
    }

    /**
     * Generate complete migration file content
     */
    private function generateMigrationFileContent(string $className, string $namespace, string $description, array $diff): string
    {
        return "<?php

declare(strict_types=1);

namespace {$namespace};

use Fduarte42\\Aurum\\Migration\\AbstractMigration;
use Fduarte42\\Aurum\\Connection\\ConnectionInterface;

final class {$className} extends AbstractMigration
{
    public function getVersion(): string
    {
        return '" . substr($className, 7) . "';
    }

    public function getDescription(): string
    {
        return '{$description}';
    }

    public function up(ConnectionInterface \$connection): void
    {
{$diff['up']}    }

    public function down(ConnectionInterface \$connection): void
    {
{$diff['down']}    }
}
";
    }
}
