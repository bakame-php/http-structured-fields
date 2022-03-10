<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;

/**
 * @implements StructuredFieldContainer<int, Item|InnerList>
 */
final class OrderedList implements StructuredFieldContainer
{
    /** @var array<Item|InnerList>  */
    private array $elements;

    /**
     * @param iterable<Item|InnerList> $elements
     */
    public function __construct(iterable $elements = [])
    {
        $this->elements = [];
        foreach ($elements as $element) {
            $this->push($element);
        }
    }

    public static function fromField(string $field): self
    {
        $instance = new self();

        $field = trim($field, ' ');
        if ('' === $field) {
            return $instance;
        }

        foreach (explode(',', $field) as $element) {
            $element = trim($element, " \t");
            $instance->push(self::parseItemOrInnerList($element));
        }

        return $instance;
    }

    private static function parseItemOrInnerList(string $element): Item|InnerList
    {
        if (str_starts_with($element, '(')) {
            return InnerList::fromField($element);
        }

        return Item::fromField($element);
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    public function keys(): array
    {
        return [];
    }

    public function getByKey(string $key): Item|InnerList|null
    {
        throw InvalidOffset::dueToKeyNotFound($key);
    }

    public function hasKey(string $key): bool
    {
        return false;
    }

    public function getByIndex(int $index): Item|InnerList|null
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return $this->elements[$offset];
    }

    public function hasIndex(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

    public function unshift(Item|InnerList ...$elements): void
    {
        $this->elements = [...$elements, ...$this->elements];
    }

    public function push(Item|InnerList ...$elements): void
    {
        $this->elements = [...$this->elements, ...$elements];
    }

    public function insert(int $index, Item|InnerList $element, Item|InnerList ...$elements): void
    {
        array_unshift($elements, $element);
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$elements),
            count($this->elements) === $offset => $this->push(...$elements),
            default => array_splice($this->elements, $offset, 0, $elements),
        };
    }

    public function replace(int $index, Item|InnerList $element): void
    {
        $offset = $this->filterIndex($index);
        if (null === $offset || !$this->hasIndex($offset)) {
            throw InvalidOffset::dueToIndexNotFound($index);
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

    public function toField(): string
    {
        $returnValue = [];
        foreach ($this->elements as $key => $element) {
            $returnValue[] = match (true) {
                $element instanceof Item && true === $element->value() => $key.$element->parameters()->toField(),
                default => !is_int($key) ? $key.'='.$element->toField() : $element->toField(),
            };
        }

        return implode(', ', $returnValue);
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
