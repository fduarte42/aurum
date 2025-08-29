# Repository Guidelines

Aurum is a modern PHP 8.4+ ORM inspired by Doctrine, featuring an advanced type system, lazy-loading proxies, and a robust migration system.

## Project Structure & Module Organization

- **`src/`**: Core implementation using PSR-4 namespace `Fduarte42\Aurum`.
    - **`EntityManager.php`**: Primary API for entity management and persistence.
    - **`UnitOfWork/`**: Manages entity states and coordinates database synchronization.
    - **`Metadata/`**: Handles entity mapping discovered via PHP 8.4 attributes.
    - **`Proxy/`**: Implements Lazy-Ghost pattern for efficient lazy loading.
    - **`Query/`**: SQL-based QueryBuilder with DQL-like join capabilities.
    - **`Schema/` & `Migration/`**: Handles database schema generation and versioned migrations.
- **`bin/aurum-cli.php`**: Unified CLI tool for schema and migration management.
- **`examples/`**: Comprehensive usage examples for core features.
- **`tests/`**: Partitioned into `Unit` and `Integration` suites.

## Build, Test, and Development Commands

- **Install dependencies**: `composer install`
- **Run tests**: `composer test` (runs PHPUnit)
- **Static analysis**: `composer phpstan`
- **Style check**: `composer cs-check` (runs PHP_CodeSniffer)
- **Style fix**: `composer cs-fix` (runs PHPCBF)
- **Schema generation**: `php bin/aurum-cli.php schema generate`
- **Migration diff**: `php bin/aurum-cli.php migration diff --name="MigrationName"`

## Coding Style & Naming Conventions

- **Strict Typing**: Mandatory `declare(strict_types=1);` in all PHP files.
- **Modern PHP**: Extensively uses PHP 8.4 features including constructor property promotion and asymmetric visibility (`public private(set)`).
- **Standards**: Adheres to PSR-12/PER coding standards, enforced by `phpcs`.
- **Type Safety**: PHPStan (level 9/max recommended) for static analysis.

## Testing Guidelines

- **Framework**: PHPUnit 11+.
- **Organization**:
    - `tests/Unit`: Tests for isolated components using mocks where appropriate.
    - `tests/Integration`: Tests requiring actual database connections (defaults to SQLite `:memory:`).
- **Execution**: Run specific suite with `vendor/bin/phpunit --testsuite Unit`.

## Commit & Pull Request Guidelines

- **Conventions**: Follow simple descriptive prefixes: `fix:`, `refactor:`, `feat:`, or `docs:`.
- **Quality**: Ensure `composer test`, `composer phpstan`, and `composer cs-check` pass before committing.
