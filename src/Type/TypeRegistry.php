<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Type;

use Fduarte42\Aurum\Exception\ORMException;

/**
 * Registry for managing type implementations
 */
class TypeRegistry
{
    /** @var array<string, TypeInterface> */
    private array $types = [];

    /** @var array<string, string> */
    private array $phpTypeMapping = [];

    public function __construct()
    {
        $this->registerDefaultTypes();
    }

    /**
     * Register a type implementation
     */
    public function registerType(string $name, TypeInterface $type): void
    {
        $this->types[$name] = $type;
    }

    /**
     * Get a type implementation by name
     */
    public function getType(string $name): TypeInterface
    {
        if (!isset($this->types[$name])) {
            throw ORMException::unknownType($name);
        }

        return $this->types[$name];
    }

    /**
     * Check if a type is registered
     */
    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * Get all registered type names
     */
    public function getTypeNames(): array
    {
        return array_keys($this->types);
    }

    /**
     * Infer type from PHP type hint
     */
    public function inferTypeFromPHPType(string $phpType): ?string
    {
        // Remove nullable indicator
        $phpType = ltrim($phpType, '?');
        
        // Handle union types - take the first non-null type
        if (str_contains($phpType, '|')) {
            $types = explode('|', $phpType);
            foreach ($types as $type) {
                $type = trim($type);
                if ($type !== 'null') {
                    $phpType = $type;
                    break;
                }
            }
        }

        // Direct mapping
        if (isset($this->phpTypeMapping[$phpType])) {
            return $this->phpTypeMapping[$phpType];
        }

        // Check each type for compatibility
        foreach ($this->types as $typeName => $type) {
            if ($type->isCompatibleWithPHPType($phpType)) {
                return $typeName;
            }
        }

        return null;
    }

    /**
     * Register default types
     */
    private function registerDefaultTypes(): void
    {
        // Basic types
        $this->registerType('string', new \Fduarte42\Aurum\Type\Basic\StringType());
        $this->registerType('integer', new \Fduarte42\Aurum\Type\Basic\IntegerType());
        $this->registerType('float', new \Fduarte42\Aurum\Type\Basic\FloatType());
        $this->registerType('boolean', new \Fduarte42\Aurum\Type\Basic\BooleanType());
        $this->registerType('text', new \Fduarte42\Aurum\Type\Basic\TextType());
        $this->registerType('json', new \Fduarte42\Aurum\Type\Basic\JsonType());
        $this->registerType('uuid', new \Fduarte42\Aurum\Type\Basic\UuidType());

        // Decimal types
        $this->registerType('decimal', new \Fduarte42\Aurum\Type\Decimal\BigDecimalType());
        $this->registerType('decimal_ext', new \Fduarte42\Aurum\Type\Decimal\ExtDecimalType());
        $this->registerType('decimal_string', new \Fduarte42\Aurum\Type\Decimal\StringDecimalType());

        // Date/Time types
        $this->registerType('date', new \Fduarte42\Aurum\Type\DateTime\DateType());
        $this->registerType('time', new \Fduarte42\Aurum\Type\DateTime\TimeType());
        $this->registerType('datetime', new \Fduarte42\Aurum\Type\DateTime\DateTimeType());
        $this->registerType('datetime_tz', new \Fduarte42\Aurum\Type\DateTime\DateTimeWithTimezoneType());

        // PHP type mappings
        $this->phpTypeMapping = [
            'string' => 'string',
            'int' => 'integer',
            'integer' => 'integer',
            'float' => 'float',
            'double' => 'float',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'array' => 'json',
            'DateTimeImmutable' => 'datetime',
            'DateTime' => 'datetime',
            'DateTimeInterface' => 'datetime',
            'Ramsey\\Uuid\\UuidInterface' => 'uuid',
            'Brick\\Math\\BigDecimal' => 'decimal',
            'Decimal\\Decimal' => 'decimal_ext',
        ];
    }
}
