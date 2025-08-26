<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Cli\Command;

use Fduarte42\Aurum\Cli\Command\MigrationDiffCommand;
use PHPUnit\Framework\TestCase;

class MigrationDiffCommandTest extends TestCase
{
    private MigrationDiffCommand $command;

    protected function setUp(): void
    {
        // Skip initialization for most tests to avoid connection issues
        // Individual tests can create commands as needed
    }

    public function testGetName(): void
    {
        $command = $this->createCommand();
        $this->assertEquals('migration:diff', $command->getName());
    }

    public function testGetDescription(): void
    {
        $command = $this->createCommand();
        $this->assertStringContainsString('Compare database schema', $command->getDescription());
    }

    public function testGetHelp(): void
    {
        $command = $this->createCommand();
        $help = $command->getHelp();
        $this->assertStringContainsString('Aurum Migration Diff Generator', $help);
        $this->assertStringContainsString('--entities', $help);
        $this->assertStringContainsString('--namespace', $help);
        $this->assertStringContainsString('--name', $help);
        $this->assertStringContainsString('--preview', $help);
    }

    public function testValidateOptionsWithValidOptions(): void
    {
        $command = $this->createCommand();
        $options = ['entities' => 'User', 'preview' => true];
        $errors = $command->validateOptions($options);
        $this->assertEmpty($errors);
    }

    public function testValidateOptionsWithConflictingEntityOptions(): void
    {
        $command = $this->createCommand();
        $options = ['entities' => 'User', 'namespace' => 'App\\Entity'];
        $errors = $command->validateOptions($options);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot specify both --entities and --namespace', $errors[0]);
    }

    public function testValidateOptionsWithConflictingOutputOptions(): void
    {
        $command = $this->createCommand();
        $options = ['name' => 'TestMigration', 'output' => 'test.php'];
        $errors = $command->validateOptions($options);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot specify multiple output options', $errors[0]);
    }

    public function testValidateOptionsWithNameAndPreview(): void
    {
        $command = $this->createCommand();
        $options = ['name' => 'TestMigration', 'preview' => true];
        $errors = $command->validateOptions($options);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot specify multiple output options', $errors[0]);
    }

    public function testValidateOptionsWithOutputAndPreview(): void
    {
        $command = $this->createCommand();
        $options = ['output' => 'test.php', 'preview' => true];
        $errors = $command->validateOptions($options);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot specify multiple output options', $errors[0]);
    }

    public function testExecuteWithNoEntities(): void
    {
        $command = $this->createCommand();
        $options = ['entities' => 'NonExistentEntity'];
        $result = $command->execute($options);
        $this->assertEquals(1, $result);
    }

    public function testExecuteWithValidPreviewOption(): void
    {
        $command = $this->createCommand();

        $options = [
            'entities' => 'NonExistentEntity',
            'preview' => true
        ];

        // This should fail gracefully since the entity doesn't exist
        $result = $command->execute($options);
        $this->assertEquals(1, $result);
    }

    public function testExecuteWithException(): void
    {
        // Test that the command handles exceptions gracefully
        // The constructor will throw an exception due to invalid driver
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Unsupported database driver: invalid_driver');

        new MigrationDiffCommand([
            'connection' => [
                'driver' => 'invalid_driver'
            ]
        ]);
    }

    public function testValidateOptionsWithAllValidOutputModes(): void
    {
        $command = $this->createCommand();
        $validOptions = [
            ['preview' => true],
            ['name' => 'TestMigration'],
            ['output' => 'test.php'],
            [] // Default to preview
        ];

        foreach ($validOptions as $options) {
            $errors = $command->validateOptions($options);
            $this->assertEmpty($errors, "Options should be valid: " . json_encode($options));
        }
    }

    public function testValidateOptionsWithNamespaceOnly(): void
    {
        $command = $this->createCommand();
        $options = ['namespace' => 'App\\Entity', 'preview' => true];
        $errors = $command->validateOptions($options);
        $this->assertEmpty($errors);
    }

    public function testValidateOptionsWithNoEntitySelection(): void
    {
        $command = $this->createCommand();
        // Should be valid - will auto-discover entities
        $options = ['preview' => true];
        $errors = $command->validateOptions($options);
        $this->assertEmpty($errors);
    }

    /**
     * Create a command instance for testing
     */
    private function createCommand(): MigrationDiffCommand
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ],
            'migrations' => [
                'directory' => 'tests/fixtures/migrations',
                'namespace' => 'TestMigrations'
            ]
        ];
        return new MigrationDiffCommand($config);
    }
}
