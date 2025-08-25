<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Tests\Unit;

use Fduarte42\Aurum\Exception\ORMException;
use Fduarte42\Aurum\Tests\Fixtures\User;
use PHPUnit\Framework\TestCase;

class ORMExceptionTest extends TestCase
{
    public function testEntityNotFound(): void
    {
        $exception = ORMException::entityNotFound(User::class, 'test-id');
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Entity of type "Fduarte42\Aurum\Tests\Fixtures\User"', $exception->getMessage());
        $this->assertStringContainsString('with identifier "test-id" not found', $exception->getMessage());
    }

    public function testEntityNotManaged(): void
    {
        $user = new User('test@example.com', 'Test User');
        $exception = ORMException::entityNotManaged($user);
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Entity of type "Fduarte42\Aurum\Tests\Fixtures\User"', $exception->getMessage());
        $this->assertStringContainsString('is not managed by the EntityManager', $exception->getMessage());
    }

    public function testInvalidEntityClass(): void
    {
        $exception = ORMException::invalidEntityClass('InvalidClass');
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Class "InvalidClass" is not a valid entity class', $exception->getMessage());
    }

    public function testMetadataNotFound(): void
    {
        $exception = ORMException::metadataNotFound('SomeClass');
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Metadata for class "SomeClass" not found', $exception->getMessage());
    }

    public function testTransactionNotActive(): void
    {
        $exception = ORMException::transactionNotActive();
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertEquals('No active transaction found.', $exception->getMessage());
    }

    public function testTransactionAlreadyActive(): void
    {
        $exception = ORMException::transactionAlreadyActive();
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertEquals('A transaction is already active.', $exception->getMessage());
    }

    public function testSavepointNotSupported(): void
    {
        $exception = ORMException::savepointNotSupported();
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertEquals('Savepoints are not supported by the current database platform.', $exception->getMessage());
    }

    public function testInvalidSavepointName(): void
    {
        $exception = ORMException::invalidSavepointName('invalid_name');
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Invalid savepoint name: "invalid_name"', $exception->getMessage());
    }

    public function testConnectionFailed(): void
    {
        $exception = ORMException::connectionFailed('Connection timeout');
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Database connection failed: Connection timeout', $exception->getMessage());
    }

    public function testQueryFailed(): void
    {
        $sql = 'SELECT * FROM invalid_table';
        $message = 'Table does not exist';
        $exception = ORMException::queryFailed($sql, $message);
        
        $this->assertInstanceOf(ORMException::class, $exception);
        $this->assertStringContainsString('Query failed: Table does not exist', $exception->getMessage());
        $this->assertStringContainsString('SQL: SELECT * FROM invalid_table', $exception->getMessage());
    }
}
