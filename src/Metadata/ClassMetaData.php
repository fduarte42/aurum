<?php

namespace Fduarte42\Aurum\Metadata;

use Fduarte42\Aurum\Mapping\Column;
use Fduarte42\Aurum\Mapping\Id;
use Fduarte42\Aurum\Mapping\ManyToOne;
use Fduarte42\Aurum\Mapping\OneToMany;
use Fduarte42\Aurum\Mapping\Table;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class ClassMetadata
{
    public string $class;
    public string $table;
    /** @var array<string, ReflectionProperty> */
    public array $fields = []; // propertyName => ReflectionProperty
    public ?string $idField = null;
    public bool $idGenerated = false;
    public array $columnNames = []; // prop => columnName
    /** @var array<string, array> */
    public array $relations = []; // propertyName => [type, targetEntity, mappedBy/inversedBy, cascade]

    /**
     * @throws ReflectionException
     */
    public function __construct(string $class)
    {
        $this->class = $class;
        $rc = new ReflectionClass($class);

        // Table
        $attrs = $rc->getAttributes(Table::class);
        if ($attrs) {
            $this->table = $attrs[0]->newInstance()->name;
        } else {
            // fallback: snake_case class short name
            $this->table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $rc->getShortName()));
        }

        foreach ($rc->getProperties() as $prop) {
            $this->processProperty($prop);
        }
    }

    private function processProperty(ReflectionProperty $prop): void
    {
        $propertyName = $prop->getName();
        $this->fields[$propertyName] = $prop;

        if ($colAttrs = $prop->getAttributes(Column::class)) {
            $this->columnNames[$propertyName] = $colAttrs[0]->newInstance()->name ?? $propertyName;
        }

        if ($idAttr = $prop->getAttributes(Id::class)) {
            $this->idField = $propertyName;
            $this->idGenerated = $idAttr[0]->newInstance()->isGenerated;
        }

        // Process OneToMany relations
        if ($oneToManyAttrs = $prop->getAttributes(OneToMany::class)) {
            $oneToMany = $oneToManyAttrs[0]->newInstance();
            $this->relations[$propertyName] = [
                'type' => 'oneToMany',
                'targetEntity' => $oneToMany->targetEntity,
                'mappedBy' => $oneToMany->mappedBy,
                'cascade' => $oneToMany->cascade
            ];
        }

        // Process ManyToOne relations
        if ($manyToOneAttrs = $prop->getAttributes(ManyToOne::class)) {
            $manyToOne = $manyToOneAttrs[0]->newInstance();
            $this->relations[$propertyName] = [
                'type' => 'manyToOne',
                'targetEntity' => $manyToOne->targetEntity,
                'inversedBy' => $manyToOne->inversedBy,
                'cascade' => $manyToOne->cascade
            ];
        }
    }
}