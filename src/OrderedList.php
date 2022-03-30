<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;
use function array_map;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_array;

/**
 * @implements IteratorAggregate<array-key, Item|InnerList>
 */
final class OrderedList implements Countable, IteratorAggregate, StructuredField
{
    /** @var array<Item|InnerList>  */
    private array $members;

    private function __construct(Item|InnerList ...$members)
    {
        $this->members = $members;
    }

    /**
     * @param array{members:array<Item|InnerList>} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties['members']);
    }

    public static function from(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        return self::fromList($members);
    }

    /**
     * @param iterable<InnerList|Item|ByteSequence|Token|bool|int|float|string> $members
     */
    public static function fromList(iterable $members = []): self
    {
        $newMembers = [];
        foreach ($members as $member) {
            $newMembers[] = self::filterMember($member);
        }

        return new self(...$newMembers);
    }

    private static function filterMember(InnerList|Item|ByteSequence|Token|bool|int|float|string $member): InnerList|Item
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1
     */
    public static function fromHttpValue(string $httpValue): self
    {
        return self::fromList(array_map(
            fn (mixed $value): mixed => is_array($value) ? InnerList::fromList(...$value) : $value,
            Parser::parseList($httpValue)
        ));
    }

    public function toHttpValue(): string
    {
        return implode(', ', array_map(fn (InnerList|Item $member): string => $member->toHttpValue(), $this->members));
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
     * @return Iterator<Item|InnerList>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $item) {
            yield $item;
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

    public function get(int $index): Item|InnerList
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return $this->members[$offset];
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...array_map(self::filterMember(...), $members), ...$this->members];

        return $this;
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...$this->members, ...array_map(self::filterMember(...), $members)];

        return $this;
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $index,
        InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members
    ): self {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            default => array_splice($this->members, $offset, 0, array_map(self::filterMember(...), $members)),
        };

        return $this;
    }

    /**
     * Replaces the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $index, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        if (null === ($offset = $this->filterIndex($index))) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->members[$offset] = self::filterMember($member);

        return $this;
    }

    /**
     * Deletes members associated with the list of instance indexes.
     */
    public function remove(int ...$indexes): self
    {
        $offsets = array_filter(
            array_map(fn (int $index): int|null => $this->filterIndex($index), $indexes),
            fn (int|null $index): bool => null !== $index
        );

        foreach ($offsets as $offset) {
            unset($this->members[$offset]);
        }

        if ([] !== $offsets) {
            $this->members = array_values($this->members);
        }

        return $this;
    }

    /**
     * Removes all members from the instance.
     */
    public function clear(): self
    {
        $this->members = [];

        return $this;
    }
}
