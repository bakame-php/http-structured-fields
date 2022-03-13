<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<array-key, Item>
 */
final class InnerList implements Countable, IteratorAggregate, StructuredField, SupportsParameters
{
    /** @var array<Item>  */
    private array $elements;

    public function __construct(private Parameters $parameters, Item ...$elements)
    {
        $this->elements = $elements;
    }

    /**
     * @param iterable<Item|ByteSequence|Token|bool|int|float|string>        $elements
     * @param iterable<string,Item|ByteSequence|Token|bool|int|float|string> $parameters
     */
    public static function fromElements(iterable $elements = [], iterable $parameters = []): self
    {
        $newElements = [];
        foreach ($elements as $element) {
            $newElements[] = self::convertItem($element);
        }

        return new self(Parameters::fromAssociative($parameters), ...$newElements);
    }

    public static function fromHttpValue(string $httpValue): self
    {
        $field = trim($httpValue);

        if (1 !== preg_match("/^\((?<content>.*)\)(?<parameters>[^,]*)/", $field, $found)) {
            throw new SyntaxError("The HTTP textual representation `$httpValue` for a inner list contains invalid characters.");
        }

        if ('' !== $found['parameters'] && !str_starts_with($found['parameters'], ';')) {
            throw new SyntaxError("The HTTP textual representation `$httpValue` for a inner list contains invalid characters.");
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
            Parameters::fromHttpValue($found['parameters']),
            ...array_filter(array_map(
                fn (string $field): Item|null => '' === $field ? null : Item::fromHttpValue($field),
                $components
            ))
        );
    }

    public function toHttpValue(): string
    {
        $returnArray = array_map(fn (Item $value): string => $value->toHttpValue(), $this->elements);

        return '('.implode(' ', $returnArray).')'.$this->parameters->toHttpValue();
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    /**
     * @return Iterator<Item>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $item) {
            yield $item;
        }
    }

    public function has(int $index): bool
    {
        return null !== $this->filterIndex($index);
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

    public function get(int $index): Item
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return $this->elements[$offset];
    }

    public function unshift(Item|ByteSequence|Token|bool|int|float|string ...$elements): void
    {
        $this->elements = [...array_map(self::convertItem(...), $elements), ...$this->elements];
    }

    private static function convertItem(Item|ByteSequence|Token|bool|int|float|string $item): Item
    {
        return match (true) {
            $item instanceof Item => $item,
            default => Item::from($item),
        };
    }

    public function push(Item|ByteSequence|Token|bool|int|float|string ...$elements): void
    {
        foreach (array_map(self::convertItem(...), $elements) as $element) {
            $this->elements[] = $element;
        }
    }

    public function insert(int $index, Item|ByteSequence|Token|bool|int|float|string ...$elements): void
    {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$elements),
            count($this->elements) === $offset => $this->push(...$elements),
            default => array_splice($this->elements, $offset, 0, array_map(self::convertItem(...), $elements)),
        };
    }

    public function replace(int $index, Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        if (!$this->has($index)) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->elements[$this->filterIndex($index)] = self::convertItem($element);
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

    public function clear(): void
    {
        $this->elements = [];
    }

    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            foreach ($other as $value) {
                $this->push($value);
                $this->parameters->merge($other->parameters());
            }
        }
    }
}
