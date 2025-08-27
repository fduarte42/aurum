<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Implementation of inheritance mapping metadata
 */
class InheritanceMapping implements InheritanceMappingInterface
{
    /** @var array<string> */
    private array $childClassNames = [];

    /** @var array<string, string> */
    private array $discriminatorMap = [];

    public function __construct(
        private readonly string $strategy,
        private readonly string $discriminatorColumn,
        private readonly string $discriminatorType,
        private readonly int $discriminatorLength,
        private readonly string $rootClassName,
        private readonly ?string $parentClassName = null
    ) {
        // Add the root class to the discriminator map
        $this->discriminatorMap[$this->getDiscriminatorValue($this->rootClassName)] = $this->rootClassName;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function getDiscriminatorColumn(): string
    {
        return $this->discriminatorColumn;
    }

    public function getDiscriminatorType(): string
    {
        return $this->discriminatorType;
    }

    public function getDiscriminatorLength(): int
    {
        return $this->discriminatorLength;
    }

    public function isRootClass(): bool
    {
        return $this->parentClassName === null;
    }

    public function getRootClassName(): string
    {
        return $this->rootClassName;
    }

    public function getParentClassName(): ?string
    {
        return $this->parentClassName;
    }

    public function getChildClassNames(): array
    {
        return $this->childClassNames;
    }

    public function getDiscriminatorValue(string $className): string
    {
        // Use the fully qualified class name as discriminator value
        return $className;
    }

    public function getClassNameForDiscriminatorValue(string $discriminatorValue): string
    {
        return $this->discriminatorMap[$discriminatorValue] ?? $discriminatorValue;
    }

    public function getDiscriminatorMap(): array
    {
        return $this->discriminatorMap;
    }

    public function addChildClass(string $className): void
    {
        if (!in_array($className, $this->childClassNames, true)) {
            $this->childClassNames[] = $className;
            $this->discriminatorMap[$this->getDiscriminatorValue($className)] = $className;
        }
    }

    public function isInHierarchy(string $className): bool
    {
        if ($className === $this->rootClassName) {
            return true;
        }

        return in_array($className, $this->childClassNames, true);
    }

    /**
     * Get all classes in the hierarchy (root + children)
     * 
     * @return array<string>
     */
    public function getAllClassNames(): array
    {
        return array_merge([$this->rootClassName], $this->childClassNames);
    }

    /**
     * Check if the given class is a child class (not the root)
     */
    public function isChildClass(string $className): bool
    {
        return in_array($className, $this->childClassNames, true);
    }
}
