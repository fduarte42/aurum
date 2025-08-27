<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Migration;

use Fduarte42\Aurum\Migration\MigrationConfiguration;
use Fduarte42\Aurum\Migration\MigrationException;
use Fduarte42\Aurum\Migration\MigrationGenerator;
use PHPUnit\Framework\TestCase;

class MigrationGeneratorTest extends TestCase
{
    private string $tempDir;
    private MigrationConfiguration $configuration;
    private MigrationGenerator $generator;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aurum_migration_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->configuration = new MigrationConfiguration($this->tempDir, 'TestMigrations');
        $this->generator = new MigrationGenerator($this->configuration);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testGenerate(): void
    {
        $description = 'Create users table';
        $version = $this->generator->generate($description);

        $this->assertMatchesRegularExpression('/^\d{14}$/', $version);

        $filePath = $this->configuration->getMigrationFilePath($version);
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('namespace TestMigrations;', $content);
        $this->assertStringContainsString("class Version{$version}", $content);
        $this->assertStringContainsString($description, $content);
        $this->assertStringContainsString("return '{$version}';", $content);
    }

    public function testGenerateVersion(): void
    {
        $version = $this->generator->generateVersion();

        $this->assertMatchesRegularExpression('/^\d{14}$/', $version);
        $this->assertEquals(14, strlen($version));

        // Test that consecutive calls generate different versions
        usleep(1000000); // 1 second delay
        $version2 = $this->generator->generateVersion();
        $this->assertNotEquals($version, $version2);
    }

    public function testGetDefaultTemplate(): void
    {
        $template = $this->generator->getDefaultTemplate();

        $this->assertStringContainsString('<NAMESPACE>', $template);
        $this->assertStringContainsString('<CLASS_NAME>', $template);
        $this->assertStringContainsString('<VERSION>', $template);
        $this->assertStringContainsString('<DESCRIPTION>', $template);
        $this->assertStringContainsString('AbstractMigration', $template);
        $this->assertStringContainsString('public function up(', $template);
        $this->assertStringContainsString('public function down(', $template);
    }

    public function testValidateDescription(): void
    {
        $this->expectNotToPerformAssertions();
        $this->generator->validateDescription('Valid description');
    }

    public function testValidateDescriptionEmpty(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration description cannot be empty');
        $this->generator->validateDescription('');
    }

    public function testValidateDescriptionWhitespace(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration description cannot be empty');
        $this->generator->validateDescription('   ');
    }

    public function testValidateDescriptionTooLong(): void
    {
        $longDescription = str_repeat('a', 256);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration description cannot be longer than 255 characters');
        $this->generator->validateDescription($longDescription);
    }

    public function testValidateDescriptionInvalidCharacters(): void
    {
        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration description contains invalid characters');
        $this->generator->validateDescription('Invalid <script> description');
    }

    public function testGenerateWithCustomTemplate(): void
    {
        $customTemplate = '<?php
namespace <NAMESPACE>;
class <CLASS_NAME> {
    // Custom template for <DESCRIPTION>
    // Version: <VERSION>
}';

        $templatePath = $this->tempDir . '/custom_template.php';
        file_put_contents($templatePath, $customTemplate);

        $this->configuration->setMigrationTemplate($templatePath);

        $description = 'Test migration';
        $version = $this->generator->generate($description, $customTemplate);

        $filePath = $this->configuration->getMigrationFilePath($version);
        $content = file_get_contents($filePath);

        $this->assertStringContainsString('namespace TestMigrations;', $content);
        $this->assertStringContainsString("class Version{$version}", $content);
        $this->assertStringContainsString('Custom template for Test migration', $content);
        $this->assertStringContainsString("Version: {$version}", $content);
    }

    public function testGenerateFileAlreadyExists(): void
    {
        $description = 'Test migration';
        $version = '20231201120000';

        // Create a file with the same version
        $filePath = $this->configuration->getMigrationFilePath($version);
        file_put_contents($filePath, '<?php // existing file');

        // Mock the generator to return a specific version
        $generator = new class($this->configuration) extends MigrationGenerator {
            public function generateVersion(): string
            {
                return '20231201120000';
            }
        };

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration file already exists');
        $generator->generate($description);
    }

    public function testGenerateWithInvalidDirectory(): void
    {
        // Skip this test in Docker environments where filesystem permissions are unreliable
        if ($this->isRunningInDocker()) {
            $this->markTestSkipped('Filesystem permission tests are unreliable in Docker environments');
        }

        $invalidConfig = new MigrationConfiguration('/nonexistent/directory', 'TestMigrations');
        $generator = new MigrationGenerator($invalidConfig);

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Migration directory not found');
        $generator->generate('Test migration');
    }

    public function testTemplateReplacements(): void
    {
        $description = 'Create users table';
        $version = $this->generator->generate($description);

        $filePath = $this->configuration->getMigrationFilePath($version);
        $content = file_get_contents($filePath);

        // Check all template replacements
        $this->assertStringContainsString('namespace TestMigrations;', $content);
        $this->assertStringContainsString("class Version{$version}", $content);
        $this->assertStringContainsString("return '{$version}';", $content);
        $this->assertStringContainsString("return 'Create users table';", $content);
        $this->assertStringNotContainsString('<NAMESPACE>', $content);
        $this->assertStringNotContainsString('<CLASS_NAME>', $content);
        $this->assertStringNotContainsString('<VERSION>', $content);
        $this->assertStringNotContainsString('<DESCRIPTION>', $content);
    }

    public function testValidDescriptionCharacters(): void
    {
        $validDescriptions = [
            'Simple description',
            'Description with numbers 123',
            'Description with-dashes_and_underscores',
            'Description with (parentheses) and [brackets]',
            'Description with {braces}',
            'Description with dots...',
            'Description, with, commas',
        ];

        foreach ($validDescriptions as $description) {
            $this->expectNotToPerformAssertions();
            $this->generator->validateDescription($description);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Check if running in a Docker environment where filesystem permissions are unreliable
     */
    private function isRunningInDocker(): bool
    {
        // Check for common Docker environment indicators
        return file_exists('/.dockerenv') ||
               getenv('DOCKER_CONTAINER') !== false ||
               getenv('container') !== false ||
               (function_exists('posix_getuid') && posix_getuid() === 0); // Running as root
    }
}
