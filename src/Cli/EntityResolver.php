<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Cli;

use Fduarte42\Aurum\Metadata\MetadataFactory;

/**
 * Service for resolving and discovering entity classes
 */
class EntityResolver
{
    public function __construct(
        private readonly MetadataFactory $metadataFactory
    ) {
    }

    /**
     * Resolve entity classes based on provided options
     */
    public function resolveEntities(array $options): array
    {
        if (!empty($options['entities'])) {
            return $this->resolveEntityClasses($this->parseEntities($options['entities']));
        }
        
        if (!empty($options['namespace'])) {
            return $this->getEntitiesFromNamespace($options['namespace']);
        }
        
        // Auto-discover all entities
        return $this->autoDiscoverEntities();
    }

    /**
     * Parse entities string into array
     */
    private function parseEntities(string $entitiesStr): array
    {
        return array_map('trim', explode(',', $entitiesStr));
    }

    /**
     * Resolve entity class names to full class names
     */
    private function resolveEntityClasses(array $entities): array
    {
        $entityClasses = [];
        
        foreach ($entities as $entity) {
            $candidates = [
                $entity, // Assume fully qualified
                "App\\Entity\\{$entity}",
                "Entity\\{$entity}",
                "Entities\\{$entity}",
                "Model\\{$entity}",
                "Models\\{$entity}",
            ];

            $resolved = false;
            foreach ($candidates as $candidate) {
                if (class_exists($candidate)) {
                    // Verify it's actually an entity
                    try {
                        $this->metadataFactory->getMetadataFor($candidate);
                        $entityClasses[] = $candidate;
                        $resolved = true;
                        break;
                    } catch (\Exception $e) {
                        // Not an entity, try next candidate
                        continue;
                    }
                }
            }
            
            if (!$resolved) {
                throw new \InvalidArgumentException("Entity class not found: {$entity}");
            }
        }

        return $entityClasses;
    }

    /**
     * Get all entities from a specific namespace
     */
    private function getEntitiesFromNamespace(string $namespace): array
    {
        $entityClasses = [];
        
        // Normalize namespace (ensure it ends with backslash)
        $namespace = rtrim($namespace, '\\') . '\\';
        
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
        
        if (empty($entityClasses)) {
            throw new \InvalidArgumentException("No entities found in namespace: {$namespace}");
        }
        
        return $entityClasses;
    }

    /**
     * Auto-discover all registered entities
     */
    private function autoDiscoverEntities(): array
    {
        $entityClasses = [];
        
        // Get all declared classes
        $allClasses = get_declared_classes();
        
        foreach ($allClasses as $class) {
            // Skip built-in PHP classes and common vendor classes
            if ($this->shouldSkipClass($class)) {
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
        
        if (empty($entityClasses)) {
            throw new \RuntimeException("No entities found. Make sure your entities are loaded and have proper metadata.");
        }
        
        return $entityClasses;
    }

    /**
     * Check if a class should be skipped during auto-discovery
     */
    private function shouldSkipClass(string $class): bool
    {
        $skipPrefixes = [
            'Fduarte42\\Aurum\\',
            'PHPUnit\\',
            'Composer\\',
            'Symfony\\',
            'Doctrine\\',
            'Ramsey\\',
            'Decimal\\',
            'Psr\\',
            'Fig\\',
            'DeepCopy\\',
            'SebastianBergmann\\',
            'Theseer\\',
            'Webmozart\\',
            'PharIo\\',
            'Prophecy\\',
            'Mockery\\',
        ];
        
        foreach ($skipPrefixes as $prefix) {
            if (strpos($class, $prefix) === 0) {
                return true;
            }
        }
        
        // Skip anonymous classes
        if (strpos($class, 'class@anonymous') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Get entity count summary
     */
    public function getEntitySummary(array $entityClasses): string
    {
        $count = count($entityClasses);
        $names = array_map(function($class) {
            return (new \ReflectionClass($class))->getShortName();
        }, $entityClasses);
        
        return "Found {$count} " . ($count === 1 ? 'entity' : 'entities') . ": " . implode(', ', $names);
    }
}
