<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
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
 * @implements MemberList<int, Value|InnerList<int, Value>>
 * @phpstan-import-type DataType from Item
 */
final class OuterList implements MemberList
{
    /** @var list<Value|InnerList<int, Value>> */
    private array $members;

    private function __construct(InnerList|Value ...$members)
    {
        $this->members = array_values($members);
    }

    /**
     * @param StructuredField|iterable<Value|DataType>|DataType ...$members
     */
    public static function from(iterable|StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        return new self(...array_map(self::filterMember(...), $members));
    }

    /**
     * @param iterable<InnerList<int, Value>|list<Value|DataType>|Value|DataType> $members
     */
    public static function fromList(iterable $members = []): self
    {
        return new self(...array_map(self::filterMember(...), [...$members]));
    }

    /**
     * @param StructuredField|iterable<Value|DataType>|DataType $member
     */
    private static function filterMember(iterable|StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): InnerList|Value
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Value => $member,
            is_iterable($member) => InnerList::fromList($member),
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
        return self::from(...array_map(
            fn ($value) => is_array($value) ? InnerList::fromList(...$value) : $value,
            Parser::parseList($httpValue)
        ));
    }

    public function toHttpValue(): string
    {
        return implode(', ', array_map(fn (StructuredField $member): string => $member->toHttpValue(), $this->members));
    }

    public function __toString(): string
    {
        return $this->toHttpValue();
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
     * @return Iterator<int, Value|InnerList<int, Value>>
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
        if (!is_int($index)) {
            return null;
        }

        $max = count($this->members);

        return match (true) {
            [] === $this->members,
            0 > $max + $index,
            0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    public function get(string|int $offset): Value|InnerList
    {
        $index = $this->filterIndex($offset);
        if (null === $index) {
            throw InvalidOffset::dueToIndexNotFound($offset);
        }

        return $this->members[$index];
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        return new self(...[...array_map(self::filterMember(...), array_values($members)), ...$this->members]);
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        return new self(...[...$this->members, ...array_map(self::filterMember(...), array_values($members))]);
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        $offset = $this->filterIndex($index);

        return match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            default => (function (array $newMembers) use ($offset, $members) {
                array_splice($newMembers, $offset, 0, array_map(self::filterMember(...), $members));

                return new self(...$newMembers);
            })($this->members),
        };
    }

    /**
     * Replaces the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        if (null === ($offset = $this->filterIndex($index))) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $members = $this->members;
        $members[$offset] = self::filterMember($member);

        return new self(...$members);
    }

    /**
     * Deletes members associated with the list of instance indexes.
     */
    public function remove(int ...$indexes): static
    {
        $offsets = array_filter(
            array_map(fn (int $index): int|null => $this->filterIndex($index), $indexes),
            fn (int|null $index): bool => null !== $index
        );

        $members = $this->members;
        foreach ($offsets as $offset) {
            unset($members[$offset]);
        }

        return new self(...$members);
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param int $offset
     *
     * @return Value|InnerList<int, Value>
     */
    public function offsetGet(mixed $offset): InnerList|Value
    {
        return $this->get($offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }
}
