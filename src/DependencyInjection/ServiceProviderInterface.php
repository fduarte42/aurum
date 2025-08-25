<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\DependencyInjection;

use Psr\Container\ContainerInterface;

/**
 * Service provider interface for DI container integration
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container
     */
    public function register(ContainerInterface $container): void;

    /**
     * Get the services provided by this provider
     *
     * @return array<string>
     */
    public function getProvidedServices(): array;
}
