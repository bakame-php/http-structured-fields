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
    /** @var array<Item> */
    private array $members;

    public function __construct(private Parameters $parameters, Item ...$members)
    {
        $this->members = $members;
    }

    /**
     * @param array{members:array<Item>, parameters:Parameters} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['parameters'], ...$properties['members']);
    }

    /**
     * @param iterable<Item|ByteSequence|Token|bool|int|float|string>        $members
     * @param iterable<string,Item|ByteSequence|Token|bool|int|float|string> $parameters
     */
    public static function fromMembers(iterable $members = [], iterable $parameters = []): self
    {
        $newMembers = [];
        foreach ($members as $member) {
            $newMembers[] = self::convertItem($member);
        }

        return new self(Parameters::fromAssociative($parameters), ...$newMembers);
    }

    public function toHttpValue(): string
    {
        $returnArray = array_map(fn (Item $value): string => $value->toHttpValue(), $this->members);

        return '('.implode(' ', $returnArray).')'.$this->parameters->toHttpValue();
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function count(): int
    {
        return count($this->members);
    }

    public function isEmpty(): bool
    {
        return [] === $this->members;
    }

    /**
     * @return Iterator<Item>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $member) {
            yield $member;
        }
    }

    public function has(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

    private function filterIndex(int $index): int|null
    {
        $max = count($this->members);

        return match (true) {
            [] === $this->members, 0 > $max + $index, 0 > $max - $index - 1 => null,
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

        return $this->members[$offset];
    }

    /**
     * Insert members at the beginning of the list.
     */
    public function unshift(Item|ByteSequence|Token|bool|int|float|string ...$members): void
    {
        $this->members = [...array_map(self::convertItem(...), $members), ...$this->members];
    }

    private static function convertItem(Item|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    /**
     * Insert members at the end of the list.
     */
    public function push(Item|ByteSequence|Token|bool|int|float|string ...$members): void
    {
        foreach (array_map(self::convertItem(...), $members) as $member) {
            $this->members[] = $member;
        }
    }

    /**
     * Replace the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $index, Item|ByteSequence|Token|bool|int|float|string ...$members): void
    {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            default => array_splice($this->members, $offset, 0, array_map(self::convertItem(...), $members)),
        };
    }

    public function replace(int $index, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        if (!$this->has($index)) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->members[$this->filterIndex($index)] = self::convertItem($member);
    }

    /**
     * Delete members associated with the list of instance indexes.
     */
    public function remove(int ...$indexes): void
    {
        foreach (array_map(fn (int $index): int|null => $this->filterIndex($index), $indexes) as $index) {
            if (null !== $index) {
                unset($this->members[$index]);
            }
        }

        $this->members = array_values($this->members);
    }

    /**
     * Remove all members from the instance.
     */
    public function clear(): void
    {
        $this->members = [];
    }

    /**
     * Merge multiple instances.
     */
    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...$other->members];
            $this->parameters->merge($other->parameters());
        }
    }
}
