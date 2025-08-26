<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli;

use Fduarte42\Aurum\DependencyInjection\ContainerBuilder;
use Fduarte42\Aurum\Metadata\MetadataFactory;
use Fduarte42\Aurum\Connection\ConnectionInterface;

/**
 * Abstract base class for CLI commands
 */
abstract class AbstractCommand implements CommandInterface
{
    protected array $config;
    protected MetadataFactory $metadataFactory;
    protected ConnectionInterface $connection;

    public function __construct(array $config = [])
    {
        $this->config = $this->mergeDefaultConfig($config);
        $this->initializeServices();
    }

    /**
     * Initialize ORM services
     */
    protected function initializeServices(): void
    {
        $container = ContainerBuilder::createORM($this->config);
        $this->metadataFactory = $container->get(MetadataFactory::class);
        $this->connection = $container->get(ConnectionInterface::class);
    }

    /**
     * Merge with default configuration
     */
    protected function mergeDefaultConfig(array $config): array
    {
        $defaults = [
            'connection' => [
                'driver' => 'sqlite',
                'path' => ':memory:'
            ]
        ];

        return array_replace_recursive($defaults, $config);
    }

    /**
     * Parse entities string into array
     */
    protected function parseEntities(string $entitiesStr): array
    {
        return array_map('trim', explode(',', $entitiesStr));
    }

    /**
     * Resolve entity class names to full class names
     */
    protected function resolveEntityClasses(array $entities): array
    {
        $entityClasses = [];
        
        foreach ($entities as $entity) {
            $candidates = [
                $entity,
                "App\\Entity\\{$entity}",
                "Entity\\{$entity}",
                "Entities\\{$entity}",
                "Model\\{$entity}",
                "Models\\{$entity}",
            ];

            foreach ($candidates as $candidate) {
                if (class_exists($candidate)) {
                    $entityClasses[] = $candidate;
                    break;
                }
            }
        }

        return $entityClasses;
    }

    /**
     * Get all entities from a specific namespace
     */
    protected function getEntitiesFromNamespace(string $namespace): array
    {
        $entityClasses = [];
        
        // Get all declared classes
        $allClasses = get_declared_classes();
        
        foreach ($allClasses as $class) {
            // Check if class is in the specified namespace
            if (strpos($class, $namespace) === 0) {
                // Check if it's an entity by trying to get metadata
                try {
                    $this->metadataFactory->getMetadataFor($class);
                    $entityClasses[] = $class;
                } catch (\Exception $e) {
                    // Not an entity, skip
                    continue;
                }
            }
        }
        
        return $entityClasses;
    }

    /**
     * Auto-discover all registered entities
     */
    protected function autoDiscoverEntities(): array
    {
        $entityClasses = [];
        
        // Get all declared classes
        $allClasses = get_declared_classes();
        
        foreach ($allClasses as $class) {
            // Skip built-in PHP classes and vendor classes
            if (strpos($class, 'Fduarte42\\Aurum\\') === 0 || 
                strpos($class, 'PHPUnit\\') === 0 ||
                strpos($class, 'Composer\\') === 0 ||
                strpos($class, 'Symfony\\') === 0 ||
                strpos($class, 'Doctrine\\') === 0) {
                continue;
            }
            
            // Try to get metadata to see if it's an entity
            try {
                $this->metadataFactory->getMetadataFor($class);
                $entityClasses[] = $class;
            } catch (\Exception $e) {
                // Not an entity, skip
                continue;
            }
        }
        
        return $entityClasses;
    }

    /**
     * Check if we're running in test mode
     */
    protected function isTestMode(): bool
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
    protected function success(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "\033[32m{$message}\033[0m\n";
        }
    }

    /**
     * Output info message
     */
    protected function info(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "{$message}\n";
        }
    }

    /**
     * Output error message
     */
    protected function error(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "\033[31m{$message}\033[0m\n";
        }
    }

    /**
     * Output warning message
     */
    protected function warning(string $message): void
    {
        if (!$this->isTestMode()) {
            echo "\033[33m{$message}\033[0m\n";
        }
    }

    /**
     * Validate common options
     */
    public function validateOptions(array $options): array
    {
        $errors = [];
        
        // Check for conflicting entity selection options
        $hasEntities = !empty($options['entities']);
        $hasNamespace = !empty($options['namespace']);
        
        if ($hasEntities && $hasNamespace) {
            $errors[] = "Cannot specify both --entities and --namespace options";
        }
        
        return $errors;
    }
}
