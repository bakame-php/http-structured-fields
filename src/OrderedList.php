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

    /**
     * @param iterable<InnerList|Item|ByteSequence|Token|bool|int|float|string> $elements
     */
    public function __construct(iterable $elements = [])
    {
        $this->elements = [];
        foreach ($elements as $element) {
            $this->push($element);
        }
    }

    public static function fromHttpValue(string $field): self
    {
        $field = trim($field, ' ');
        if ('' === $field) {
            return new self();
        }

        $reducer = function (self $carry, string $element): self {
            $carry->push(self::parseItemOrInnerList(trim($element, " \t")));

            return $carry;
        };

        return array_reduce(explode(',', $field), $reducer, new self());
    }

    private static function parseItemOrInnerList(string $element): Item|InnerList
    {
        if (str_starts_with($element, '(')) {
            return InnerList::fromHttpValue($element);
        }

        return Item::fromHttpValue($element);
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return [];
    }

    public function getByKey(string $key): Item|InnerList
    {
        throw InvalidOffset::dueToKeyNotFound($key);
    }

    public function hasKey(string $key): bool
    {
        return false;
    }

    public function getByIndex(int $index): Item|InnerList
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return $this->elements[$offset];
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

    public function hasIndex(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

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

    public function push(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$elements): void
    {
        $this->elements = [...$this->elements, ...array_map(self::filterElement(...), $elements)];
    }

    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            $this->elements = [...$this->elements, ...$other->elements];
        }
    }

    public function insert(
        int $index,
        InnerList|Item|ByteSequence|Token|bool|int|float|string $element,
        InnerList|Item|ByteSequence|Token|bool|int|float|string ...$elements
    ): void {
        array_unshift($elements, $element);
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$elements),
            count($this->elements) === $offset => $this->push(...$elements),
            default => array_splice($this->elements, $offset, 0, array_map(self::filterElement(...), $elements)),
        };
    }

    public function replace(int $index, InnerList|Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        $offset = $this->filterIndex($index);
        if (null === $offset || !$this->hasIndex($offset)) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->elements[$offset] = self::filterElement($element);
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

    public function count(): int
    {
        return count($this->elements);
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
}
