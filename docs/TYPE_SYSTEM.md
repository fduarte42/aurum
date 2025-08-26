# Aurum Type System

The Aurum ORM features a sophisticated type system that provides automatic type inference, multiple decimal implementations, and specialized date/time handling.

## Overview

The type system consists of several key components:

- **TypeInterface**: Base interface for all type implementations
- **TypeRegistry**: Central registry for managing type implementations
- **TypeInference**: Automatic type detection from PHP property types
- **Type Implementations**: Concrete implementations for different data types

## Automatic Type Inference

One of the most powerful features is automatic type inference from PHP property type hints. This reduces boilerplate and makes your entities cleaner.

### Basic Type Inference

```php
<?php

#[Entity(table: 'users')]
class User
{
    #[Id]
    #[Column] // Inferred as 'uuid' from UuidInterface
    private ?UuidInterface $id = null;

    #[Column] // Inferred as 'string' with default length 255
    private string $email;

    #[Column] // Inferred as 'integer'
    private int $age;

    #[Column] // Inferred as 'boolean'
    private bool $active = true;

    #[Column] // Inferred as 'datetime'
    private \DateTimeImmutable $createdAt;
}
```

### Smart Length and Precision Inference

The type inference system can also infer appropriate lengths and precision based on property names:

```php
<?php

class Product
{
    #[Column] // Inferred: string, length 255 (email pattern)
    private string $email;

    #[Column] // Inferred: string, length 500 (url pattern)
    private string $url;

    #[Column] // Inferred: string, length 50 (code pattern)
    private string $productCode;

    #[Column] // Inferred: decimal, precision 10, scale 2 (price pattern)
    private BigDecimal $price;

    #[Column] // Inferred: decimal, precision 5, scale 4 (rate pattern)
    private BigDecimal $taxRate;

    #[Column] // Inferred: string, no length limit (description -> TEXT)
    private string $description;
}
```

## Decimal Types

Aurum supports three different decimal implementations to suit different needs:

### 1. BigDecimal (brick/math) - Default

```php
use Brick\Math\BigDecimal;

#[Column(type: 'decimal', precision: 15, scale: 4)]
private BigDecimal $amount;

// Usage
$amount = BigDecimal::of('123.4567');
$result = $amount->plus(BigDecimal::of('10.00'));
```

**Pros:**
- Pure PHP implementation
- No external dependencies
- Excellent precision
- Rich API for mathematical operations

**Cons:**
- Slightly slower than native extensions
- More memory usage

### 2. ext-decimal Extension

```php
use Decimal\Decimal;

#[Column(type: 'decimal_ext', precision: 10, scale: 2)]
private Decimal $tax;

// Usage
$tax = new Decimal('98.76');
$result = $tax->add(new Decimal('1.24'));
```

**Pros:**
- Native C extension (fastest)
- Lower memory usage
- IEEE 754 compliant

**Cons:**
- Requires ext-decimal extension
- Less portable

### 3. String-based Decimal

```php
#[Column(type: 'decimal_string', precision: 8, scale: 3)]
private string $commission;

// Usage
$commission = '123.456';
// Manual string arithmetic or use with bcmath functions
```

**Pros:**
- No dependencies
- Maximum portability
- Can handle arbitrary precision

**Cons:**
- No built-in arithmetic operations
- Requires manual validation
- String manipulation needed for calculations

## Date/Time Types

Aurum provides specialized date/time types for different use cases:

### Date Type

Stores only the date portion (Y-m-d):

```php
#[Column(type: 'date')]
private \DateTimeImmutable $birthDate;

// Database storage: '2023-12-01'
```

### Time Type

Stores only the time portion (H:i:s):

```php
#[Column(type: 'time')]
private \DateTimeImmutable $startTime;

// Database storage: '09:30:00'
```

### DateTime Type

Standard date and time without timezone information:

```php
#[Column(type: 'datetime')]
private \DateTimeImmutable $createdAt;

// Database storage: '2023-12-01 09:30:00'
```

### Timezone-Aware DateTime

Stores both datetime and timezone information as JSON:

```php
#[Column(type: 'datetime_tz')]
private \DateTimeImmutable $scheduledAt;

// Database storage: {"datetime": "2023-12-01 09:30:00", "timezone": "America/New_York"}
```

## Custom Types

You can create custom types by implementing the `TypeInterface`:

```php
<?php

use Fduarte42\Aurum\Type\AbstractType;

class ColorType extends AbstractType
{
    public function getName(): string
    {
        return 'color';
    }

    public function convertToPHPValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return new Color($value); // Your custom Color class
    }

    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Color) {
            return $value->toHex();
        }

        return (string) $value;
    }

    public function getSQLDeclaration(array $options = []): string
    {
        return 'CHAR(7)'; // #RRGGBB format
    }

    public function isCompatibleWithPHPType(string $phpType): bool
    {
        return $phpType === Color::class;
    }

    protected function getGenericSQLType(array $options = []): string
    {
        return 'CHAR(7)';
    }
}
```

Register your custom type:

```php
$typeRegistry = $container->get(TypeRegistry::class);
$typeRegistry->registerType('color', new ColorType());
```

## Type Registry

The `TypeRegistry` manages all type implementations and provides type inference:

```php
$typeRegistry = new TypeRegistry();

// Register a custom type
$typeRegistry->registerType('my_type', new MyCustomType());

// Check if a type exists
if ($typeRegistry->hasType('decimal')) {
    $type = $typeRegistry->getType('decimal');
}

// Infer type from PHP type
$inferredType = $typeRegistry->inferTypeFromPHPType('string'); // Returns 'string'
$inferredType = $typeRegistry->inferTypeFromPHPType('DateTimeImmutable'); // Returns 'datetime'
```

## Best Practices

### 1. Use Type Inference When Possible

```php
// ✅ Good - Clean and concise
#[Column]
private string $name;

// ❌ Unnecessary - Type can be inferred
#[Column(type: 'string')]
private string $name;
```

### 2. Be Explicit for Complex Types

```php
// ✅ Good - Explicit about decimal implementation and precision
#[Column(type: 'decimal', precision: 15, scale: 4)]
private BigDecimal $amount;

// ✅ Good - Explicit about timezone-aware datetime
#[Column(type: 'datetime_tz')]
private \DateTimeImmutable $scheduledAt;
```

### 3. Choose the Right Decimal Type

- Use `decimal` (BigDecimal) for general-purpose decimal arithmetic
- Use `decimal_ext` when performance is critical and ext-decimal is available
- Use `decimal_string` for maximum portability or when working with external systems

### 4. Use Appropriate Date/Time Types

- Use `date` for birth dates, event dates, etc.
- Use `time` for opening hours, durations, etc.
- Use `datetime` for timestamps without timezone concerns
- Use `datetime_tz` when timezone information is important

## Migration from Legacy Code

If you're migrating from a system without type inference:

1. **Remove explicit type declarations** where they can be inferred
2. **Update decimal handling** to use one of the three decimal types
3. **Consider timezone requirements** for datetime fields
4. **Test thoroughly** to ensure type conversions work as expected

The type system is designed to be backward compatible, so existing explicit type declarations will continue to work.
