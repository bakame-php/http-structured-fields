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
use function is_int;

/**
 * @implements IteratorAggregate<array-key, Item|InnerList>
 */
final class OrderedList implements Countable, IteratorAggregate, StructuredField
{
    /** @var array<Item|InnerList>  */
    private array $members;

    public function __construct(Item|InnerList ...$members)
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

    /**
     * @param iterable<InnerList|Item|ByteSequence|Token|bool|int|float|string> $members
     */
    public static function fromMembers(iterable $members = []): self
    {
        $newMembers = [];
        foreach ($members as $member) {
            $newMembers[] = self::filterMember($member);
        }

        return new self(...$newMembers);
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1
     */
    public static function fromHttpValue(string $httpValue): self
    {
        return self::fromMembers(Parser::parseList($httpValue));
    }

    public function toHttpValue(): string
    {
        $returnValue = [];
        foreach ($this->members as $key => $member) {
            $returnValue[] = match (true) {
                $member instanceof Item && true === $member->value() => $key.$member->parameters()->toHttpValue(),
                default => !is_int($key) ? $key.'='.$member->toHttpValue() : $member->toHttpValue(),
            };
        }

        return implode(', ', $returnValue);
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
     * Insert members at the beginning of the list.
     */
    public function unshift(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members): void
    {
        $this->members = [...array_map(self::filterMember(...), $members), ...$this->members];
    }

    private static function filterMember(InnerList|Item|ByteSequence|Token|bool|int|float|string $member): InnerList|Item
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    /**
     * Insert members at the end of the list.
     */
    public function push(InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members): void
    {
        $this->members = [...$this->members, ...array_map(self::filterMember(...), $members)];
    }

    /**
     * Insert members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $index,
        InnerList|Item|ByteSequence|Token|bool|int|float|string ...$members
    ): void {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            default => array_splice($this->members, $offset, 0, array_map(self::filterMember(...), $members)),
        };
    }

    /**
     * Replace the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $index, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        if (!$this->has($index)) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->members[$this->filterIndex($index)] = self::filterMember($member);
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
        }
    }
}
