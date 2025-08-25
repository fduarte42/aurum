<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Migration;

use DateTime;

/**
 * Generates new migration files
 */
class MigrationGenerator implements MigrationGeneratorInterface
{
    public function __construct(
        private readonly MigrationConfiguration $configuration
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $description, ?string $template = null): string
    {
        $this->validateDescription($description);
        $this->configuration->validate();

        $version = $this->generateVersion();
        $className = 'Version' . $version;
        $filePath = $this->configuration->getMigrationFilePath($version);

        if (file_exists($filePath)) {
            throw new MigrationException("Migration file already exists: {$filePath}");
        }

        $content = $this->generateMigrationContent($className, $description, $template);

        if (file_put_contents($filePath, $content) === false) {
            throw new MigrationException("Failed to write migration file: {$filePath}");
        }

        return $version;
    }

    /**
     * {@inheritdoc}
     */
    public function generateVersion(): string
    {
        return (new DateTime())->format('YmdHis');
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultTemplate(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace <NAMESPACE>;

use Fduarte42\Aurum\Migration\AbstractMigration;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * <DESCRIPTION>
 */
final class <CLASS_NAME> extends AbstractMigration
{
    public function getVersion(): string
    {
        return '<VERSION>';
    }

    public function getDescription(): string
    {
        return '<DESCRIPTION>';
    }

    public function up(ConnectionInterface $connection): void
    {
        // Add your migration logic here
        // Example:
        // $this->addSql('CREATE TABLE example (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        
        // Or use the schema builder:
        // $this->schemaBuilder->createTable('example')
        //     ->addColumn('id', 'integer', ['primary_key' => true])
        //     ->addColumn('name', 'string', ['length' => 255, 'not_null' => true])
        //     ->create();
    }

    public function down(ConnectionInterface $connection): void
    {
        // Add your rollback logic here
        // Example:
        // $this->addSql('DROP TABLE example');
        
        // Or use the schema builder:
        // $this->schemaBuilder->dropTable('example');
    }
}
PHP;
    }

    /**
     * {@inheritdoc}
     */
    public function validateDescription(string $description): void
    {
        if (empty(trim($description))) {
            throw new MigrationException('Migration description cannot be empty');
        }

        if (strlen($description) > 255) {
            throw new MigrationException('Migration description cannot be longer than 255 characters');
        }

        // Check for invalid characters that might cause issues in class names or file names
        if (preg_match('/[^\w\s\-_.,()[\]{}]/', $description)) {
            throw new MigrationException('Migration description contains invalid characters');
        }
    }

    /**
     * Generate the migration file content
     */
    private function generateMigrationContent(string $className, string $description, ?string $template = null): string
    {
        $template = $template ?: $this->getCustomTemplate() ?: $this->getDefaultTemplate();
        $version = substr($className, 7); // Remove "Version" prefix

        $replacements = [
            '<NAMESPACE>' => $this->configuration->getMigrationsNamespace(),
            '<CLASS_NAME>' => $className,
            '<VERSION>' => $version,
            '<DESCRIPTION>' => $description,
            '<DATE>' => date('Y-m-d H:i:s'),
            '<YEAR>' => date('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get custom template if configured
     */
    private function getCustomTemplate(): ?string
    {
        $templatePath = $this->configuration->getMigrationTemplate();
        
        if ($templatePath === null || !file_exists($templatePath)) {
            return null;
        }

        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new MigrationException("Failed to read migration template: {$templatePath}");
        }

        return $content;
    }
}
