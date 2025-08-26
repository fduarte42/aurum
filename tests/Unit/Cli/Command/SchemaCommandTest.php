<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Cli\Command;

use Fduarte42\Aurum\Cli\Command\SchemaCommand;
use Fduarte42\Aurum\Connection\ConnectionFactory;
use PHPUnit\Framework\TestCase;

class SchemaCommandTest extends TestCase
{
    private SchemaCommand $command;

    protected function setUp(): void
    {
        // Skip initialization for most tests to avoid connection issues
        // Individual tests can create commands as needed
    }

    public function testGetName(): void
    {
        $command = $this->createCommand();
        $this->assertEquals('schema:generate', $command->getName());
    }

    public function testGetDescription(): void
    {
        $command = $this->createCommand();
        $this->assertStringContainsString('Generate database schema code', $command->getDescription());
    }

    public function testGetHelp(): void
    {
        $command = $this->createCommand();
        $help = $command->getHelp();
        $this->assertStringContainsString('Aurum Schema Generator', $help);
        $this->assertStringContainsString('--entities', $help);
        $this->assertStringContainsString('--namespace', $help);
        $this->assertStringContainsString('--format', $help);
    }

    public function testValidateOptionsWithValidFormat(): void
    {
        $command = $this->createCommand();
        $options = ['format' => 'schema-builder'];
        $errors = $command->validateOptions($options);
        $this->assertEmpty($errors);
    }

    public function testValidateOptionsWithInvalidFormat(): void
    {
        $command = $this->createCommand();
        $options = ['format' => 'invalid'];
        $errors = $command->validateOptions($options);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid format', $errors[0]);
    }

    public function testValidateOptionsWithConflictingEntityOptions(): void
    {
        $command = $this->createCommand();
        $options = ['entities' => 'User', 'namespace' => 'App\\Entity'];
        $errors = $command->validateOptions($options);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Cannot specify both --entities and --namespace', $errors[0]);
    }

    public function testExecuteWithNoEntities(): void
    {
        $command = $this->createCommand();
        $options = ['entities' => 'NonExistentEntity'];
        $result = $command->execute($options);
        $this->assertEquals(1, $result);
    }

    public function testExecuteWithValidOptions(): void
    {
        $command = $this->createCommand();

        $options = [
            'entities' => 'NonExistentEntity',
            'format' => 'schema-builder'
        ];

        // This should fail gracefully since the entity doesn't exist
        $result = $command->execute($options);
        $this->assertEquals(1, $result);
    }

    public function testValidateOptionsWithAllValidFormats(): void
    {
        $command = $this->createCommand();
        $validFormats = ['schema-builder', 'sql', 'both'];

        foreach ($validFormats as $format) {
            $options = ['format' => $format];
            $errors = $command->validateOptions($options);
            $this->assertEmpty($errors, "Format '{$format}' should be valid");
        }
    }

    public function testValidateOptionsWithDefaultFormat(): void
    {
        $command = $this->createCommand();
        $options = []; // No format specified, should use default
        $errors = $command->validateOptions($options);
        $this->assertEmpty($errors);
    }

    public function testExecuteWithException(): void
    {
        // Test that the command handles exceptions gracefully
        // The constructor will throw an exception due to invalid driver
        $this->expectException(\Fduarte42\Aurum\Exception\ORMException::class);
        $this->expectExceptionMessage('Unsupported database driver: invalid_driver');

        new SchemaCommand([
            'connection' => [
                'driver' => 'invalid_driver'
            ]
        ]);
    }

    /**
     * Create a command instance for testing
     */
    private function createCommand(): SchemaCommand
    {
        $config = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];
        return new SchemaCommand($config);
    }
}
