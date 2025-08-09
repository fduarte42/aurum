<?php

namespace Fduarte42\Aurum\Connection;


use PDOStatement;

interface ConnectionInterface
{
    public function beginTransaction( string $uowId = 'default' ): void;

    public function commit( string $uowId = 'default' ): void;

    public function rollBack( string $uowId = 'default' ): void;

    public function prepare( string $sql ): false|PDOStatement;

    public function lastInsertId(): string;

    public function exec( string $sql ): int|false;

    public function query( string $sql ): false|PDOStatement;

    public function inTransaction(): bool;
}