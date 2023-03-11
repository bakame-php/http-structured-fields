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
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#name-lists
 *
 * @implements MemberList<int, Value|(MemberList<int, Value>&ParameterAccess)>
 * @phpstan-import-type DataType from Value
 */
final class OuterList implements MemberList
{
    /** @var list<Value|(MemberList<int, Value>&ParameterAccess)> */
    private readonly array $members;

    /**
     * @param Value|(MemberList<int, Value>&ParameterAccess) ...$members
     */
    private function __construct(MemberList|Value ...$members)
    {
        $this->members = array_values($members);
    }

    /**
     * @param StructuredField|iterable<Value|DataType>|DataType ...$members
     */
    public static function fromMembers(iterable|StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        return new self(...array_map(self::filterMember(...), $members));
    }

    /**
     * @param StructuredField|iterable<Value|DataType>|DataType $member
     *
     * @return (MemberList<int, Value>&ParameterAccess)|Value
     */
    private static function filterMember(iterable|StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): mixed
    {
        return match (true) {
            ($member instanceof MemberList && $member instanceof ParameterAccess),
            $member instanceof Value => $member,
            is_iterable($member) => InnerList::fromMembers(...$member),
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
        return self::fromMembers(...array_map(
            fn (mixed $value) => is_array($value) ? InnerList::fromMembers(...$value[0])->withParameters(Parameters::fromAssociative($value[1])) : $value,
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
     * @return array<int>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    public function has(string|int ...$keys): bool
    {
        foreach ($keys as $offset) {
            if (null === $this->filterIndex($offset)) {
                return false;
            }
        }

        return [] !== $keys;
    }

    private function filterIndex(string|int $index): int|null
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

    public function get(string|int $key): StructuredField
    {
        $index = $this->filterIndex($key);
        if (null === $index) {
            throw InvalidOffset::dueToIndexNotFound($key);
        }

        return $this->members[$index];
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
     */
    public function offsetGet(mixed $offset): mixed
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

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        if ([] === $members) {
            return $this;
        }

        return $this->newInstance([...array_map(self::filterMember(...), array_values($members)), ...$this->members]);
    }

    /**
     * @param iterable<int, Value|(MemberList<int, Value>&ParameterAccess)> $members
     */
    private function newInstance(iterable $members): self
    {
        return new self(...$members);
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        if ([] === $members) {
            return $this;
        }

        return $this->newInstance([...$this->members, ...array_map(self::filterMember(...), array_values($members))]);
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        $offset = $this->filterIndex($key);

        return match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($key),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            [] === $members => $this,
            default => (function (array $newMembers) use ($offset, $members) {
                array_splice($newMembers, $offset, 0, array_map(self::filterMember(...), $members));

                return $this->newInstance($newMembers);
            })($this->members),
        };
    }

    public function replace(int $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        if (null === ($offset = $this->filterIndex($key))) {
            throw InvalidOffset::dueToIndexNotFound($key);
        }

        $members = $this->members;
        $members[$offset] = self::filterMember($member);

        return $this->newInstance($members);
    }

    /**
     * Deletes members associated with the list of instance indexes.
     */
    public function remove(string|int ...$keys): static
    {
        $offsets = array_filter(
            array_map(
                fn (int $index): int|null => $this->filterIndex($index),
                array_filter($keys, static fn (string|int $key): bool => is_int($key))
            ),
            fn (int|null $index): bool => null !== $index
        );

        if ([] === $offsets) {
            return $this;
        }

        $members = $this->members;
        foreach ($offsets as $offset) {
            unset($members[$offset]);
        }

        return $this->newInstance($members);
    }
}
