<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type;

use ReflectionProperty;
use ReflectionNamedType;
use ReflectionUnionType;
use ReflectionIntersectionType;

/**
 * Type inference utility for automatically detecting types from PHP property declarations
 */
class TypeInference
{
    public function __construct(
        private readonly TypeRegistry $typeRegistry
    ) {
    }

    /**
     * Infer type from a reflection property
     */
    public function inferFromProperty(ReflectionProperty $property): ?string
    {
        $type = $property->getType();
        
        if ($type === null) {
            return null;
        }

        return $this->inferFromReflectionType($type);
    }

    /**
     * Infer type from a reflection type
     */
    public function inferFromReflectionType(\ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            return $this->inferFromNamedType($type);
        }

        if ($type instanceof ReflectionUnionType) {
            return $this->inferFromUnionType($type);
        }

        if ($type instanceof ReflectionIntersectionType) {
            // Intersection types are not commonly used for simple properties
            return null;
        }

        return null;
    }

    /**
     * Infer type from a named type
     */
    private function inferFromNamedType(ReflectionNamedType $type): ?string
    {
        $typeName = $type->getName();
        
        // Handle built-in types
        if ($type->isBuiltin()) {
            return $this->typeRegistry->inferTypeFromPHPType($typeName);
        }

        // Handle class types
        return $this->inferFromClassName($typeName);
    }

    /**
     * Infer type from a union type
     */
    private function inferFromUnionType(ReflectionUnionType $type): ?string
    {
        $types = $type->getTypes();
        $nonNullTypes = [];

        // Filter out null types
        foreach ($types as $unionType) {
            if ($unionType instanceof ReflectionNamedType && $unionType->getName() !== 'null') {
                $nonNullTypes[] = $unionType;
            }
        }

        // If we have exactly one non-null type, use that
        if (count($nonNullTypes) === 1) {
            return $this->inferFromNamedType($nonNullTypes[0]);
        }

        // For multiple non-null types, we can't reliably infer
        return null;
    }

    /**
     * Infer type from a class name
     */
    private function inferFromClassName(string $className): ?string
    {
        // Direct mapping for known classes
        $classMapping = [
            'DateTimeImmutable' => 'datetime',
            'DateTime' => 'datetime',
            'DateTimeInterface' => 'datetime',
            'Ramsey\\Uuid\\UuidInterface' => 'uuid',
            'Ramsey\\Uuid\\Uuid' => 'uuid',
            'Brick\\Math\\BigDecimal' => 'decimal',
            'Decimal\\Decimal' => 'decimal_ext',
        ];

        // Check direct mapping first
        if (isset($classMapping[$className])) {
            return $classMapping[$className];
        }

        // Check if any registered type is compatible with this class
        return $this->typeRegistry->inferTypeFromPHPType($className);
    }

    /**
     * Get additional type options from property analysis
     */
    public function inferTypeOptions(ReflectionProperty $property, string $inferredType): array
    {
        $options = [];

        // For string types, try to infer length from property name or docblock
        if ($inferredType === 'string') {
            $options['length'] = $this->inferStringLength($property);
        }

        // For decimal types, try to infer precision/scale from docblock
        if (str_starts_with($inferredType, 'decimal')) {
            $precisionScale = $this->inferDecimalPrecisionScale($property);
            if ($precisionScale !== null) {
                $options['precision'] = $precisionScale['precision'];
                $options['scale'] = $precisionScale['scale'];
            }
        }

        return $options;
    }

    /**
     * Infer string length from property characteristics
     */
    private function inferStringLength(ReflectionProperty $property): ?int
    {
        $propertyName = strtolower($property->getName());

        // Common patterns for different string lengths
        $lengthPatterns = [
            'email' => 255,
            'name' => 255,
            'title' => 255,
            'slug' => 255,
            'url' => 500,
            'description' => null, // Use TEXT type instead
            'content' => null, // Use TEXT type instead
            'code' => 50,
            'token' => 255,
            'hash' => 255,
        ];

        foreach ($lengthPatterns as $pattern => $length) {
            if (str_contains($propertyName, $pattern)) {
                return $length;
            }
        }

        // Default length for strings
        return 255;
    }

    /**
     * Infer decimal precision and scale from property or docblock
     */
    private function inferDecimalPrecisionScale(ReflectionProperty $property): ?array
    {
        $propertyName = strtolower($property->getName());

        // Common patterns for different decimal types
        $decimalPatterns = [
            'price' => ['precision' => 10, 'scale' => 2],
            'amount' => ['precision' => 10, 'scale' => 2],
            'cost' => ['precision' => 10, 'scale' => 2],
            'rate' => ['precision' => 5, 'scale' => 4],
            'percentage' => ['precision' => 5, 'scale' => 2],
            'weight' => ['precision' => 8, 'scale' => 3],
            'distance' => ['precision' => 10, 'scale' => 2],
        ];

        foreach ($decimalPatterns as $pattern => $config) {
            if (str_contains($propertyName, $pattern)) {
                return $config;
            }
        }

        // Default precision and scale
        return ['precision' => 10, 'scale' => 2];
    }
}
