<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use Iterator;

/**
 * @implements StructuredFieldContainer<array-key, Item|null>
 */
final class InnerList implements StructuredFieldContainer, SupportsParameters
{
    /** @var array<Item|null>  */
    private array $elements;
    private Parameters $parameters;

    /**
     * @param iterable<Item|null> $elements
     */
    public function __construct(iterable $elements = [], Parameters|null $parameters = null)
    {
        $this->elements = [];
        foreach ($elements as $element) {
            $this->push($element);
        }

        $this->parameters = $parameters ?? new Parameters();
    }

    public static function fromField(string $field): self
    {
        $field = trim($field);

        if (1 !== preg_match("/^\((?<content>.*)\)(?<parameters>[^,]*)/", $field, $found)) {
            throw new SyntaxError('Invalid inner list string.');
        }

        if ('' !== $found['parameters'] && !str_starts_with($found['parameters'], ';')) {
            throw new SyntaxError('Invalid inner list string.');
        }

        /** @var string $content */
        $content = preg_replace('/[ ]+/', ' ', $found['content']);
        $content = trim($content);

        $components = array_reduce(explode(' ', $content), function (array $components, string $component): array {
            if ([] === $components) {
                return [$component];
            }

            $lastIndex = count($components) - 1;

            if (str_starts_with($component, ';')) {
                $components[$lastIndex] .= $component;

                return $components;
            }

            $lastAddition = $components[$lastIndex];
            if (str_ends_with($lastAddition, ';')) {
                $components[$lastIndex] .= $component;

                return $components;
            }

            $components[] = $component;

            return $components;
        }, []);

        return new self(
            array_map(
                fn (string $field): Item|null => '' === $field ? null : Item::fromField($field),
                $components
            ),
            Parameters::fromField($found['parameters'])
        );
    }

    public function unshift(Item|null ...$elements): void
    {
        $this->elements = [...$elements, ...$this->elements];
    }

    public function push(Item|null ...$elements): void
    {
        foreach ($elements as $element) {
            $this->elements[] = $element;
        }
    }

    public function insert(int $index, Item|null ...$elements): void
    {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw new InvalidIndex('Invalid index `'.$index.'`'),
            0 === $offset => $this->unshift(...$elements),
            count($this->elements) === $offset => $this->push(...$elements),
            default => array_splice($this->elements, $offset, 0, $elements),
        };
    }

    public function replace(int $index, Item|null $element): void
    {
        $offset = $this->filterIndex($index);
        if (null === $offset || !$this->indexExists($offset)) {
            throw new InvalidIndex('The index does not exist for this instance.');
        }

        $this->elements[$offset] = $element;
    }

    public function remove(int ...$indexes): void
    {
        foreach (array_map(fn (int $index): int|null => $this->filterIndex($index), $indexes) as $index) {
            if (null !== $index) {
                unset($this->elements[$index]);
            }
        }

        $this->elements = array_values($this->elements);
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    public function findByKey(string $key): Item|null
    {
        return null;
    }

    public function findByIndex(int $index): Item|null
    {
        return $this->elements[$this->filterIndex($index)] ?? null;
    }

    public function indexExists(int $index): bool
    {
        $offset = $this->filterIndex($index);

        return null !== $offset && array_key_exists($offset, $this->elements);
    }

    /**
     * @return array<Item|null>
     */
    public function value(): array
    {
        return $this->elements;
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * @return Iterator<Item|null>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $item) {
            yield $item;
        }
    }

    public function canonical(): string
    {
        $returnArray = array_map(fn (Item|null $value): string|null => $value?->canonical(), $this->elements);
        $returnValue = '('.implode(' ', $returnArray).')';
        $serializedParameter = $this->parameters->canonical();
        if ('' !== $serializedParameter) {
            $returnValue .= $serializedParameter;
        }

        return $returnValue;
    }

    private function filterIndex(int $index): int|null
    {
        $max = count($this->elements);

        return match (true) {
            [] === $this->elements, 0 > $max + $index, 0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }
}
