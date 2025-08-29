<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Query;

use Fduarte42\Aurum\Hydration\EntityHydratorInterface;
use Fduarte42\Aurum\Metadata\MetadataFactory;

/**
 * Lazy iterator that yields hydrated entity objects one at a time
 *
 * This iterator provides memory-efficient streaming of query results
 * by hydrating entities on-demand as they are accessed, rather than
 * loading all results into memory at once.
 *
 * Works with any Iterator source, providing consistent Iterator-based processing.
 */
class EntityResultIterator implements \Iterator, \Countable
{
    private \Iterator $sourceIterator;
    private MetadataFactory $metadataFactory;
    private EntityHydratorInterface $entityHydrator;
    private string $rootEntityClass;
    private ?object $current = null;
    private int $position = 0;
    private bool $valid = true;

    public function __construct(
        \Iterator $sourceIterator,
        MetadataFactory $metadataFactory,
        EntityHydratorInterface $entityHydrator,
        string $rootEntityClass
    ) {
        $this->sourceIterator = $sourceIterator;
        $this->metadataFactory = $metadataFactory;
        $this->entityHydrator = $entityHydrator;
        $this->rootEntityClass = $rootEntityClass;
    }

    public function current(): ?object
    {
        return $this->current;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
        $this->fetchNext();
    }

    public function rewind(): void
    {
        // Try to rewind the source iterator, but handle cases where it doesn't support rewinding
        try {
            $this->sourceIterator->rewind();
        } catch (\Error $e) {
            // PDOStatement iterator doesn't support rewinding, which is expected
            // We can only iterate once, similar to PDOStatement behavior
            if (strpos($e->getMessage(), 'does not support rewinding') !== false) {
                // If we've already started iterating, we can't rewind
                if ($this->position > 0) {
                    throw new \Error('Iterator does not support rewinding');
                }
                // If we haven't started yet, we can proceed
            } else {
                throw $e;
            }
        }

        $this->position = 0;
        $this->valid = true;
        $this->fetchNext();
    }

    public function valid(): bool
    {
        return $this->valid;
    }

    public function count(): int
    {
        // Note: This will consume the iterator if called
        // This is a limitation of PDOStatement - we can't count without iterating
        $count = 0;
        foreach ($this as $entity) {
            $count++;
        }
        return $count;
    }

    /**
     * Fetch and hydrate the next entity from the source iterator
     */
    private function fetchNext(): void
    {
        if (!$this->sourceIterator->valid()) {
            $this->valid = false;
            $this->current = null;
            return;
        }

        $row = $this->sourceIterator->current();
        $this->sourceIterator->next();

        if ($row === null || $row === false) {
            $this->valid = false;
            $this->current = null;
            return;
        }

        $this->current = $this->hydrateEntityDetached($row);
    }

    /**
     * Hydrate a single database result into a detached entity (not tracked by UnitOfWork)
     */
    private function hydrateEntityDetached(array $data): object
    {
        // Use the centralized EntityHydrator to hydrate detached entities
        return $this->entityHydrator->hydrateDetached($data, $this->rootEntityClass);
    }
}
