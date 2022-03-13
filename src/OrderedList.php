<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<array-key, Item|InnerList>
 */
final class OrderedList implements Countable, IteratorAggregate, StructuredField
{
    /** @var array<Item|InnerList>  */
    private array $elements;

    public function __construct(Item|InnerList ...$elements)
    {
        $this->elements = $elements;
    }

    /**
     * @param iterable<InnerList|Item|ByteSequence|Token|bool|int|float|string> $elements
     */
    public static function fromElements(iterable $elements = []): self
    {
        $newElements = [];
        foreach ($elements as $element) {
            $newElements[] = self::filterElement($element);
        }

        return new self(...$newElements);
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1
     */
    public static function fromHttpValue(string $httpValue): self
    {
        $httpValue = trim($httpValue, ' ');
        if ('' === $httpValue) {
            return new self();
        }

        $parser = fn (string $element): Item|InnerList => str_starts_with($element, '(')
            ? InnerList::fromHttpValue($element)
            : Item::fromHttpValue($element);

        $reducer = function (self $carry, string $element) use ($parser): self {
            $carry->push($parser(trim($element, " \t")));

            return $carry;
        };

        return array_reduce(explode(',', $httpValue), $reducer, new self());
    }

    public function toHttpValue(): string
    {
        $returnValue = [];
        foreach ($this->elements as $key => $element) {
            $returnValue[] = match (true) {
                $element instanceof Item && true === $element->value() => $key.$element->parameters()->toHttpValue(),
                default => !is_int($key) ? $key.'='.$element->toHttpValue() : $element->toHttpValue(),
            };
        }

        return implode(', ', $returnValue);
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
     * @return Iterator<Item|InnerList>
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

    public function get(int $index): Item|InnerList
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return $this->elements[$offset];
    }

    /**
     * Insert elements at the beginning of the list.
     */
    public function unshift(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$elements): void
    {
        $this->elements = [...array_map(self::filterElement(...), $elements), ...$this->elements];
    }

    private static function filterElement(InnerList|Item|ByteSequence|Token|bool|int|float|string $element): InnerList|Item
    {
        return match (true) {
            $element instanceof InnerList, $element instanceof Item => $element,
            default => Item::from($element),
        };
    }

    /**
     * Insert elements at the end of the list.
     */
    public function push(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$elements): void
    {
        $this->elements = [...$this->elements, ...array_map(self::filterElement(...), $elements)];
    }

    /**
     * Insert elements starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $index,
        InnerList|Item|ByteSequence|Token|bool|int|float|string ...$elements
    ): void {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$elements),
            count($this->elements) === $offset => $this->push(...$elements),
            default => array_splice($this->elements, $offset, 0, array_map(self::filterElement(...), $elements)),
        };
    }

    /**
     * Replace the element associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $index, InnerList|Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        if (!$this->has($index)) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->elements[$this->filterIndex($index)] = self::filterElement($element);
    }

    /**
     * Delete elements associated with the list of instance indexes.
     */
    public function remove(int ...$indexes): void
    {
        foreach (array_map(fn (int $index): int|null => $this->filterIndex($index), $indexes) as $index) {
            if (null !== $index) {
                unset($this->elements[$index]);
            }
        }

        $this->elements = array_values($this->elements);
    }

    /**
     * Remove all elements from the instance.
     */
    public function clear(): void
    {
        $this->elements = [];
    }

    /**
     * Merge multiple instances.
     */
    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            $this->elements = [...$this->elements, ...$other->elements];
        }
    }
}
