<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Proxy;

/**
 * Proxy factory interface for creating lazy loading proxies
 */
interface ProxyFactoryInterface
{
    /**
     * Create a lazy loading proxy for an entity
     *
     * @template T of object
     * @param class-string<T> $className
     * @param mixed $identifier
     * @param callable(): T $initializer
     * @return T
     */
    public function createProxy(string $className, mixed $identifier, callable $initializer): object;

    /**
     * Check if an object is a proxy
     */
    public function isProxy(object $object): bool;

    /**
     * Initialize a proxy if it's not already initialized
     */
    public function initializeProxy(object $proxy): void;

    /**
     * Check if a proxy is initialized
     */
    public function isProxyInitialized(object $proxy): bool;

    /**
     * Get the real class name of a proxy
     *
     * @param object|class-string $objectOrClass
     * @return class-string
     */
    public function getRealClass(object|string $objectOrClass): string;

    /**
     * Get the identifier of a proxy without initializing it
     */
    public function getProxyIdentifier(object $proxy): mixed;
}
