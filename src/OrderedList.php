<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;
use Throwable;
use TypeError;
use function array_filter;
use function array_map;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_array;

/**
 * @implements StructuredFieldList<int, Item|InnerList<int, Item>>
 */
final class OrderedList implements StructuredFieldList
{
    /** @var array<int, Item|InnerList<int, Item>>  */
    private array $members;

    private function __construct(Item|InnerList ...$members)
    {
        $this->members = array_values($members);
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

    private static function filterMember(StructuredField|ByteSequence|Token|bool|int|float|string $member): InnerList|Item
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Item => self::filterForbiddenState($member),
            $member instanceof StructuredField => throw new TypeError('Expecting a "'.Item::class.'" or a "'.InnerList::class.'" instance; received a "'.$member::class.'" instead.'),
            default => Item::from($member),
        };
    }

    private static function filterForbiddenState(InnerList|Item $member): InnerList|Item
    {
        foreach ($member->parameters as $offset => $item) {
            if ($item->parameters->hasMembers()) {
                throw new ForbiddenStateError('Parameter member `"'.$offset.'"` is in invalid state; Parameters instances can only contain bare items.');
            }
        }

        if ($member instanceof Item) {
            return $member;
        }

        foreach ($member as $item) {
            self::filterForbiddenState($item);
        }

        return $member;
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

    public function hasMembers(): bool
    {
        return !$this->isEmpty();
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
        return is_int($offset) && null !== $this->filterIndex($offset);
    }

    private function filterIndex(int|string $index): int|null
    {
        $max = count($this->members);

        return match (true) {
            is_string($index) => null,
            [] === $this->members, 0 > $max + $index, 0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    /**
     * Returns all containers Item values.
     *
     * @return array<int, InnerList<int, Item>|float|int|bool|string>
     */
    public function values(): array
    {
        $mapper = function (Item|InnerList $item): InnerList|float|int|bool|string|null {
            try {
                $member = self::filterForbiddenState($item);

                return $member instanceof Item ? $member->value() : $member;
            } catch (Throwable) {
                return null;
            }
        };

        return array_filter(array_map($mapper, $this->members), fn (mixed $value): bool => null !== $value);
    }

    /**
     * Returns the Item value of a specific key if it exists and is valid otherwise returns null.
     *
     * @return InnerList<int, Item>|float|int|bool|string|null
     */
    public function value(string|int $offset): InnerList|float|int|bool|string|null
    {
        try {
            $member = $this->get($offset);
        } catch (Throwable) {
            return null;
        }

        if ($member instanceof Item) {
            return $member->value();
        }

        return $member;
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

        return self::filterForbiddenState($this->members[$index]);
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(StructuredField|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...array_map(self::filterMember(...), array_values($members)), ...$this->members];

        return $this;
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(StructuredField|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...$this->members, ...array_map(self::filterMember(...), array_values($members))];

        return $this;
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $index, StructuredField|ByteSequence|Token|bool|int|float|string ...$members): self
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
    public function replace(int $index, StructuredField|ByteSequence|Token|bool|int|float|string $member): self
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
     * @param InnerList<int, Item>|Item|ByteSequence|Token|bool|int|float|string $value  the member to add
     *
     * @see ::push
     * @see ::replace
     */
    public function offsetSet(mixed $offset, $value): void
    {
        if (null !== $offset) {
            $this->replace($offset, $value);

            return;
        }

        $this->push($value);
    }
}
