<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\DependencyInjection;

use Fduarte42\Aurum\EntityManagerInterface;
use Psr\Container\ContainerInterface;

/**
 * Simple container builder for ORM services
 */
class ContainerBuilder implements ContainerInterface
{
    private array $services = [];
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Add a service provider
     */
    public function addServiceProvider(ServiceProviderInterface $provider): self
    {
        $provider->register($this);
        return $this;
    }

    /**
     * Set a service
     */
    public function set(string $id, mixed $service): self
    {
        $this->services[$id] = $service;
        return $this;
    }

    /**
     * Get a service
     */
    public function get(string $id): mixed
    {
        if (!isset($this->services[$id])) {
            throw new \InvalidArgumentException("Service '{$id}' not found");
        }

        $service = $this->services[$id];

        // If it's a callable, invoke it
        if (is_callable($service)) {
            $service = $service($this);
            $this->services[$id] = $service; // Cache the result
        }

        return $service;
    }

    /**
     * Check if a service exists
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * Set a service (for ContainerInterface compatibility)
     */
    public function setService(string $id, mixed $service): void
    {
        $this->set($id, $service);
    }

    /**
     * Build the container
     */
    public function build(): ContainerInterface
    {
        // Add config as a service if not already set
        if (!isset($this->services['config'])) {
            $this->services['config'] = $this->config;
        }

        return new SimpleContainer($this->services);
    }

    /**
     * Create a pre-configured ORM container
     */
    public static function createORM(array $config = []): ContainerInterface
    {
        $builder = new self($config);
        $builder->addServiceProvider(new ORMServiceProvider($config));
        $builder->addServiceProvider(new MigrationServiceProvider());
        return $builder->build();
    }

    /**
     * Quick method to get an EntityManager
     */
    public static function createEntityManager(array $config = []): EntityManagerInterface
    {
        $container = self::createORM($config);
        return $container->get(EntityManagerInterface::class);
    }
}

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
            throw new class("Service '{$id}' not found") extends \Exception implements \Psr\Container\NotFoundExceptionInterface {};
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
