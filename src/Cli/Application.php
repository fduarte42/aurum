<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli;

/**
 * Main CLI application class
 */
class Application
{
    private array $commands = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Register a command
     */
    public function addCommand(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * Run the application
     */
    public function run(array $argv): int
    {
        try {
            // Parse command line arguments
            $commandName = $argv[1] ?? 'help';

            // Check if argv[2] is a subcommand or an option
            $subCommand = null;
            $optionsStartIndex = 2;

            if (isset($argv[2]) && !str_starts_with($argv[2], '-')) {
                $subCommand = $argv[2];
                $optionsStartIndex = 3;
            }

            $options = $this->parseOptions(array_slice($argv, $optionsStartIndex));

            // Handle global options
            if (in_array('--help', $argv) || in_array('-h', $argv)) {
                return $this->showHelp($commandName, $subCommand);
            }

            if (in_array('--version', $argv) || in_array('-v', $argv)) {
                return $this->showVersion();
            }

            // Handle help command
            if ($commandName === 'help') {
                return $this->showHelp($subCommand);
            }

            // Find and execute command
            $fullCommandName = $subCommand ? "{$commandName}:{$subCommand}" : $commandName;
            
            if (!isset($this->commands[$fullCommandName])) {
                $this->error("Unknown command: {$fullCommandName}");
                $this->showAvailableCommands();
                return 1;
            }

            $command = $this->commands[$fullCommandName];

            // Validate options
            $errors = $command->validateOptions($options);
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->error("❌ {$error}");
                }
                return 1;
            }

            // Execute command
            return $command->execute($options);

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            
            if (in_array('--debug', $argv)) {
                $this->error("\nStack trace:");
                $this->error($e->getTraceAsString());
            } else {
                $this->info("💡 Use --debug flag for detailed error information");
            }
            
            return 1;
        }
    }

    /**
     * Parse command line options
     */
    private function parseOptions(array $args): array
    {
        $options = [];
        
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) {
                if (strpos($arg, '=') !== false) {
                    [$key, $value] = explode('=', substr($arg, 2), 2);
                    $options[$key] = $value;
                } else {
                    $options[substr($arg, 2)] = true;
                }
            } elseif (strpos($arg, '-') === 0) {
                $options[substr($arg, 1)] = true;
            }
        }
        
        return $options;
    }

    /**
     * Show help for a command or general help
     */
    private function showHelp(?string $commandName = null, ?string $subCommand = null): int
    {
        if ($commandName && $subCommand) {
            $fullCommandName = "{$commandName}:{$subCommand}";
            if (isset($this->commands[$fullCommandName])) {
                if (!$this->isTestMode()) {
                    echo $this->commands[$fullCommandName]->getHelp();
                }
                return 0;
            }
        }

        if ($commandName && isset($this->commands[$commandName])) {
            if (!$this->isTestMode()) {
                echo $this->commands[$commandName]->getHelp();
            }
            return 0;
        }

        $this->showGeneralHelp();
        return 0;
    }

    /**
     * Show general help
     */
    private function showGeneralHelp(): void
    {
        if ($this->isTestMode()) {
            return;
        }

        echo "🔧 Aurum CLI Tool\n";
        echo "================\n\n";
        echo "A unified command-line interface for Aurum ORM schema and migration management.\n\n";
        echo "USAGE:\n";
        echo "  php bin/aurum-cli.php <command> [subcommand] [options]\n\n";
        echo "GLOBAL OPTIONS:\n";
        echo "  --help, -h     Show help information\n";
        echo "  --version, -v  Show version information\n";
        echo "  --debug        Show detailed error information\n\n";
        echo "AVAILABLE COMMANDS:\n";

        $this->showAvailableCommands();

        echo "\nFor detailed help on a specific command:\n";
        echo "  php bin/aurum-cli.php help <command> [subcommand]\n";
        echo "  php bin/aurum-cli.php <command> [subcommand] --help\n\n";
    }

    /**
     * Show available commands
     */
    private function showAvailableCommands(): void
    {
        if ($this->isTestMode()) {
            return;
        }

        $groupedCommands = [];

        foreach ($this->commands as $name => $command) {
            if (strpos($name, ':') !== false) {
                [$group, $sub] = explode(':', $name, 2);
                $groupedCommands[$group][$sub] = $command;
            } else {
                $groupedCommands[''][$name] = $command;
            }
        }

        foreach ($groupedCommands as $group => $commands) {
            if ($group) {
                echo "  {$group}:\n";
                foreach ($commands as $subName => $command) {
                    echo "    {$group} {$subName}    {$command->getDescription()}\n";
                }
            } else {
                foreach ($commands as $name => $command) {
                    echo "  {$name}    {$command->getDescription()}\n";
                }
            }
            echo "\n";
        }
    }

    /**
     * Show version information
     */
    private function showVersion(): int
    {
        if (!$this->isTestMode()) {
            echo "Aurum CLI Tool v1.0.0\n";
            echo "Part of the Aurum ORM package\n";
        }
        return 0;
    }

    /**
     * Check if we're running in test mode
     */
    private function isTestMode(): bool
    {
        // Check if PHPUnit is running
        return defined('PHPUNIT_COMPOSER_INSTALL') ||
               (defined('PHPUNIT_VERSION')) ||
               (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') ||
               (isset($GLOBALS['argv']) && is_array($GLOBALS['argv']) &&
                (in_array('phpunit', $GLOBALS['argv']) ||
                 array_filter($GLOBALS['argv'], fn($arg) => str_contains($arg, 'phpunit'))));
    }

    /**
     * Output success message
     */
    private function success(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "\033[32m{$message}\033[0m\n";
        }
    }

    /**
     * Output info message
     */
    private function info(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "{$message}\n";
        }
    }

    /**
     * Output error message
     */
    private function error(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "\033[31m{$message}\033[0m\n";
        }
    }
}
