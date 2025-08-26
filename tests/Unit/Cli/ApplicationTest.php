<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit\Cli;

use Fduarte42\Aurum\Cli\Application;
use Fduarte42\Aurum\Cli\CommandInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ApplicationTest extends TestCase
{
    private Application $application;

    protected function setUp(): void
    {
        $this->application = new Application();
    }

    public function testAddCommand(): void
    {
        $command = $this->createMockCommand('test', 'Test command');
        
        $this->application->addCommand($command);
        
        // Test that command is registered by trying to run it
        $result = $this->application->run(['script', 'test']);
        $this->assertEquals(0, $result);
    }

    public function testRunWithHelp(): void
    {
        $result = $this->application->run(['script', 'help']);
        $this->assertEquals(0, $result);
    }

    public function testRunWithVersion(): void
    {
        $result = $this->application->run(['script', '--version']);
        $this->assertEquals(0, $result);
    }

    public function testRunWithUnknownCommand(): void
    {
        $result = $this->application->run(['script', 'unknown']);
        $this->assertEquals(1, $result);
    }

    public function testRunWithSubcommand(): void
    {
        $command = $this->createMockCommand('test:sub', 'Test subcommand');
        $this->application->addCommand($command);
        
        $result = $this->application->run(['script', 'test', 'sub']);
        $this->assertEquals(0, $result);
    }

    public function testRunWithCommandValidationError(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('getDescription')->willReturn('Test command');
        $command->method('validateOptions')->willReturn(['Validation error']);
        
        $this->application->addCommand($command);
        
        $result = $this->application->run(['script', 'test']);
        $this->assertEquals(1, $result);
    }

    public function testRunWithCommandException(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('getDescription')->willReturn('Test command');
        $command->method('validateOptions')->willReturn([]);
        $command->method('execute')->willThrowException(new \Exception('Test error'));
        
        $this->application->addCommand($command);
        
        $result = $this->application->run(['script', 'test']);
        $this->assertEquals(1, $result);
    }

    public function testParseOptionsWithLongOptions(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('getDescription')->willReturn('Test command');
        $command->method('validateOptions')->willReturn([]);
        $command->method('execute')->willReturnCallback(function($options) {
            $this->assertEquals('value', $options['option']);
            $this->assertTrue($options['flag']);
            return 0;
        });

        $this->application->addCommand($command);

        $result = $this->application->run(['script', 'test', '--option=value', '--flag']);
        $this->assertEquals(0, $result);
    }

    public function testParseOptionsWithShortOptions(): void
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn('test');
        $command->method('getDescription')->willReturn('Test command');
        $command->method('validateOptions')->willReturn([]);
        $command->method('execute')->willReturnCallback(function($options) {
            $this->assertTrue($options['h']);
            return 0;
        });
        
        $this->application->addCommand($command);
        
        $result = $this->application->run(['script', 'test', '-h']);
        $this->assertEquals(0, $result);
    }

    public function testShowHelpForSpecificCommand(): void
    {
        $command = $this->createMockCommand('test', 'Test command');
        $command->method('getHelp')->willReturn('Test help text');
        
        $this->application->addCommand($command);
        
        $result = $this->application->run(['script', 'help', 'test']);
        $this->assertEquals(0, $result);
    }

    public function testShowHelpForSubcommand(): void
    {
        $command = $this->createMockCommand('test:sub', 'Test subcommand');
        $command->method('getHelp')->willReturn('Test subcommand help');
        
        $this->application->addCommand($command);
        
        $result = $this->application->run(['script', 'help', 'test', 'sub']);
        $this->assertEquals(0, $result);
    }

    /**
     * Create a mock command
     */
    private function createMockCommand(string $name, string $description): CommandInterface|MockObject
    {
        $command = $this->createMock(CommandInterface::class);
        $command->method('getName')->willReturn($name);
        $command->method('getDescription')->willReturn($description);
        $command->method('getHelp')->willReturn("Help for {$name}");
        $command->method('validateOptions')->willReturn([]);
        $command->method('execute')->willReturn(0);
        
        return $command;
    }
}
