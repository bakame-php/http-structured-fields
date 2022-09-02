<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;
use Throwable;
use function array_filter;
use function array_map;
use function array_splice;
use function array_values;
use function count;

/**
 * @implements MemberList<int, Item>
 */
final class InnerList implements MemberList, ParameterAccess
{
    /** @var array<int, Item> */
    private array $members;

    private function __construct(
        public readonly Parameters $parameters,
        Item ...$members
    ) {
        $this->members = array_values($members);
    }

    public static function from(Item|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        return self::fromList($members);
    }

    /**
     * @param iterable<Item|ByteSequence|Token|bool|int|float|string> $members
     * @param iterable<array-key, Item|ByteSequence|Token|bool|int|float|string> $parameters
     */
    public static function fromList(iterable $members = [], iterable $parameters = []): self
    {
        $newMembers = [];
        foreach ($members as $member) {
            $newMembers[] = self::filterMember($member);
        }

        return new self(Parameters::fromAssociative($parameters), ...$newMembers);
    }

    private static function filterMember(StructuredField|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => self::filterForbiddenState($member),
            $member instanceof StructuredField => throw new InvalidArgument('Expecting a "'.Item::class.'" instance; received a "'.$member::class.'" instance instead.'),
            default => Item::from($member),
        };
    }

    private static function filterForbiddenState(Item $member): Item
    {
        foreach ($member->parameters as $offset => $item) {
            if ($item->parameters->hasMembers()) {
                throw new ForbiddenStateError('Parameter member "'.$offset.'" is in invalid state; Parameters instances can only contain bare items.');
            }
        }

        return $member;
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

    public function hasMembers(): bool
    {
        return [] !== $this->members;
    }

    /**
     * @return Iterator<array-key, Item>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    public function has(string|int $offset): bool
    {
        return is_int($offset) && null !== $this->filterIndex($offset);
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

    /**
     * Returns all containers Item values.
     *
     * @return array<int, float|int|bool|string>
     */
    public function values(): array
    {
        $result = [];
        foreach ($this->members as $offset => $item) {
            $value = $this->value($offset);
            if (null !== $value) {
                $result[$offset] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns the Item value of a specific key if it exists and is valid otherwise returns null.
     */
    public function value(string|int $offset): float|int|bool|string|null
    {
        try {
            return $this->get($offset)->value();
        } catch (Throwable) {
            return null;
        }
    }

    public function get(string|int $offset): Item
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
     * Insert members at the beginning of the list.
     */
    public function unshift(StructuredField|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members = [...array_map(self::filterMember(...), array_values($members)), ...$this->members];

        return $this;
    }

    /**
     * Insert members at the end of the list.
     */
    public function push(StructuredField|ByteSequence|Token|bool|int|float|string ...$members): self
    {
        $this->members =  [...$this->members, ...array_map(self::filterMember(...), array_values($members))];

        return $this;
    }

    /**
     * Replace the member associated with the index.
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

    public function replace(int $index, StructuredField|ByteSequence|Token|bool|int|float|string $member): self
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
     */
    public function offsetGet($offset): Item
    {
        return $this->get($offset);
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * @param Item|ByteSequence|Token|bool|int|float|string $value the member to add
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
