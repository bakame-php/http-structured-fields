<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;
use Iterator;
use Stringable;
use function array_filter;
use function array_map;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_array;

/**
 * @implements MemberList<int, Item|InnerList<int, Item>>
 */
final class OrderedList implements MemberList
{
    /** @var array<int, Item|InnerList<int, Item>>  */
    private array $members = [];

    private function __construct()
    {
    }

    public static function from(InnerList|Item|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        return self::fromList($members);
    }

    /**
     * @param iterable<InnerList<int, Item>|Item|DataType> $members
     */
    public static function fromList(iterable $members): self
    {
        $instance = new self();
        foreach ($members as $member) {
            $instance->push(self::filterMember($member));
        }

        return $instance;
    }

    private static function filterMember(InnerList|Item|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): InnerList|Item
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
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        return self::from()
            ->push(...array_map(
                fn ($value) => is_array($value) ? InnerList::fromList(...$value) : $value,
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

    public function hasNoMembers(): bool
    {
        return !$this->hasMembers();
    }

    public function hasMembers(): bool
    {
        return [] !== $this->members;
    }

    /**
     * @return Iterator<int, Item|InnerList<int, Item>>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    public function has(string|int $offset): bool
    {
        return null !== $this->filterIndex($offset);
    }

    private function filterIndex(int|string $index): int|null
    {
        $max = count($this->members);
        if (!is_int($index)) {
            return null;
        }

        return match (true) {
            [] === $this->members,
            0 > $max + $index,
            0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    public function get(string|int $offset): Item|InnerList
    {
        if (!is_int($offset)) {
            throw InvalidOffset::dueToIndexNotFound($offset);
        }

        $index = $this->filterIndex($offset);
        if (null === $index) {
            throw InvalidOffset::dueToIndexNotFound($offset);
        }

        return $this->members[$index];
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        $this->members = [...array_map(self::filterMember(...), array_values($members)), ...$this->members];

        return $this;
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        $this->members = [...$this->members, ...array_map(self::filterMember(...), array_values($members))];

        return $this;
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
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

    /**
     * Replaces the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
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

        return $this;
    }

    public function clear(): self
    {
        $this->members = [];

        return $this;
    }

    /**
     * @param int $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param int $offset
     *
     * @return Item|InnerList<int, Item>
     */
    public function offsetGet(mixed $offset): Item|InnerList
    {
        return $this->get($offset);
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * @param int|null $offset
     * @param InnerList<int, Item>|Item|DataType $value  the member to add
     *
     * @see ::push
     * @see ::replace
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null !== $offset) {
            $this->replace($offset, $value);

            return;
        }

        $this->push($value);
    }
}
