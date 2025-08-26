<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\DependencyInjection;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Simple PSR-11 container implementation
 */
class SimpleContainer implements ContainerInterface
{
    private array $services;

    public function __construct(array $services = [])
    {
        $this->services = $services;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("Service '{$id}' not found") extends \Exception implements NotFoundExceptionInterface {};
        }

        $service = $this->services[$id];

        // If it's a callable, invoke it
        if (is_callable($service)) {
            $service = $service($this);
            $this->services[$id] = $service; // Cache the result
        }

        return $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }
}
