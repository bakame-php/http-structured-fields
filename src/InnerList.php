<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Countable;
use Iterator;
use IteratorAggregate;
use function array_filter;
use function array_map;
use function array_splice;
use function array_values;
use function count;

/**
 * @implements IteratorAggregate<int, Item>
 * @implements ArrayAccess<int, Item>
 */
final class InnerList implements ArrayAccess, Countable, IteratorAggregate, StructuredField
{
    /** @var array<Item> */
    private array $members;

    private function __construct(
        public readonly Parameters $parameters,
        Item ...$members
    ) {
        $this->members = $members;
    }

    public static function from(Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        return self::fromList($members);
    }

    /**
     * @param iterable<Item|ByteSequence|Token|bool|int|float|string>        $members
     * @param iterable<string,Item|ByteSequence|Token|bool|int|float|string> $parameters
     */
    public static function fromList(iterable $members = [], iterable $parameters = []): self
    {
        $newMembers = [];
        foreach ($members as $member) {
            $newMembers[] = self::filterMember($member);
        }

        return new self(Parameters::fromAssociative($parameters), ...$newMembers);
    }

    private static function filterMember(Item|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    public static function fromHttpValue(string $httpValue): self
    {
        return InnerList::fromList(...Parser::parseInnerList($httpValue));
    }

    public function toHttpValue(): string
    {
        return '('.implode(' ', array_map(
            fn (Item $value): string => $value->toHttpValue(),
            $this->members
        )).')'.$this->parameters->toHttpValue();
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
    public function unshift(Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...array_map(self::filterMember(...), $members), ...$this->members];

        return $this;
    }

    /**
     * Insert members at the end of the list.
     */
    public function push(Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...$this->members, ...array_map(self::filterMember(...), $members)];

        return $this;
    }

    /**
     * Replace the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $index, Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $offset = $this->filterIndex($index);
        match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            default => array_splice($this->members, $offset, 0, array_map(self::filterMember(...), $members)),
        };

        return $this;
    }

    public function replace(int $index, Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        if (null === ($offset = $this->filterIndex($index))) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $this->members[$offset] = self::filterMember($member);

        return $this;
    }

    /**
     * Delete members associated with the list of instance indexes.
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

        return $this;
    }

    /**
     * Ensure the container always contains list.
     *
     * If gaps are present in the list their are removed
     * and the list gets re-indexed.
     */
    public function sanitize(): self
    {
        $this->parameters->sanitize();

        foreach ($this->members as $member) {
            $member->parameters->sanitize();
        }

        if (!array_is_list($this->members)) {
            $this->members = array_values($this->members);
        }

        return $this;
    }

    /**
     * Remove all members from the instance.
     */
    public function clear(): self
    {
        $this->members = [];

        return $this;
    }

    /**
     * @param int $offset the integer index of the member to validate.
     *
     * @see ::has
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     *
     * @param int $offset the integer index of the member to retrieve.
     *
     * @see ::get
     */
    public function offsetGet($offset): Item
    {
        return $this->get($offset);
    }

    /**
     *
     * @param int $offset the integer index of the member to remove
     *
     * @see ::remove
     *
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     *
     * @param int|null                                      $offset the integer index of member to add or update
     * @param Item|ByteSequence|Token|bool|int|float|string $value  the member to add
     *
     * @see ::push
     * @see ::replace
     */
    public function offsetSet($offset, $value): void
    {
        if (null !== $offset) {
            $this->replace($offset, $value);

            return;
        }

        $this->push($value);
    }
}
