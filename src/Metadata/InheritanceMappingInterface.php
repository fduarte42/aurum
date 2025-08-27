<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Metadata;

/**
 * Interface for inheritance mapping metadata
 */
interface InheritanceMappingInterface
{
    /**
     * Get the inheritance strategy
     */
    public function getStrategy(): string;

    /**
     * Get the discriminator column name
     */
    public function getDiscriminatorColumn(): string;

    /**
     * Get the discriminator column type
     */
    public function getDiscriminatorType(): string;

    /**
     * Get the discriminator column length
     */
    public function getDiscriminatorLength(): int;

    /**
     * Check if this is the root class of the inheritance hierarchy
     */
    public function isRootClass(): bool;

    /**
     * Get the root class name of the inheritance hierarchy
     */
    public function getRootClassName(): string;

    /**
     * Get the parent class name (null if this is the root)
     */
    public function getParentClassName(): ?string;

    /**
     * Get all child class names
     * 
     * @return array<string>
     */
    public function getChildClassNames(): array;

    /**
     * Get the discriminator value for a given class
     */
    public function getDiscriminatorValue(string $className): string;

    /**
     * Get the class name for a given discriminator value
     */
    public function getClassNameForDiscriminatorValue(string $discriminatorValue): string;

    /**
     * Get all discriminator values mapped to class names
     * 
     * @return array<string, string> Array where key is discriminator value and value is class name
     */
    public function getDiscriminatorMap(): array;

    /**
     * Add a child class to the inheritance hierarchy
     */
    public function addChildClass(string $className): void;

    /**
     * Check if a class is part of this inheritance hierarchy
     */
    public function isInHierarchy(string $className): bool;
}
