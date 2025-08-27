<?php

declare(strict_types=1);

namespace Fduarte42\Aurum\Repository;

use Fduarte42\Aurum\EntityManagerInterface;

/**
 * Iterator that yields managed entity objects one at a time
 *
 * This iterator provides memory-efficient streaming of repository results
 * by hydrating and managing entities on-demand as they are accessed.
 * Unlike QueryBuilder's EntityResultIterator, this returns managed entities
 * that are tracked by the UnitOfWork.
 *
 * Works with any Iterator source, providing consistent Iterator-based processing
 * throughout the codebase.
 */
class ManagedEntityIterator implements \Iterator, \Countable
{
    private \Iterator $sourceIterator;
    private Repository $repository;
    private EntityManagerInterface $entityManager;
    private ?object $current = null;
    private int $position = 0;
    private bool $valid = true;

    public function __construct(
        \Iterator $sourceIterator,
        Repository $repository,
        EntityManagerInterface $entityManager
    ) {
        $this->sourceIterator = $sourceIterator;
        $this->repository = $repository;
        $this->entityManager = $entityManager;
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
        // Rewind the source iterator
        $this->sourceIterator->rewind();
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
        // If source iterator is countable, use its count
        if ($this->sourceIterator instanceof \Countable) {
            return $this->sourceIterator->count();
        }
        
        // Otherwise, we need to iterate to count (this will consume the iterator)
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
        // Handle Iterator
        if (!$this->sourceIterator->valid()) {
            $this->valid = false;
            $this->current = null;
            return;
        }

        $data = $this->sourceIterator->current();
        $this->sourceIterator->next();

        if ($data === null || $data === false) {
            $this->valid = false;
            $this->current = null;
            return;
        }

        // Hydrate the entity and ensure it's managed
        if (is_array($data)) {
            // Data from PDOStatement iterator (array format)
            $entity = $this->repository->hydrateEntity($data);
        } else {
            // Data is already an entity object (from QueryBuilder iterator)
            $entity = $this->entityManager->manage($data);
        }

        $this->current = $entity;
    }

    /**
     * Convert iterator to array for backward compatibility
     * 
     * @return array<object>
     */
    public function toArray(): array
    {
        return iterator_to_array($this);
    }
}
