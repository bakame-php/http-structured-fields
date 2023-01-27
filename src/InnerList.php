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

/**
 * @implements MemberList<int, Value>
 * @phpstan-import-type DataType from Item
 */
final class InnerList implements MemberList, ParameterAccess
{
    /** @var list<Value> */
    private array $members;

    /**
     * @param iterable<Value|DataType> $members
     */
    private function __construct(private readonly Parameters $parameters, iterable $members)
    {
        $this->members = array_map(self::filterMember(...), array_values([...$members]));
    }

    /**
     * Returns a new instance.
     */
    public static function from(Value|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        return new self(Parameters::create(), $members);
    }

    /**
     * @param iterable<Value|DataType> $members
     * @param iterable<string, Value|DataType> $parameters
     */
    public static function fromList(iterable $members = [], iterable $parameters = []): self
    {
        return new self(Parameters::fromAssociative($parameters), $members);
    }

    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        return InnerList::fromList(...Parser::parseInnerList($httpValue));
    }

    public function parameters(): Parameters
    {
        return clone $this->parameters;
    }

    public function prependParameter(string $key, Value|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(string $key, Value|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    public function withoutParameter(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->delete(...$keys));
    }

    public function clearParameters(): static
    {
        return $this->withParameters($this->parameters()->clear());
    }

    public function withParameters(Parameters $parameters): static
    {
        if ($this->parameters->toHttpValue() === $parameters->toHttpValue()) {
            return $this;
        }

        return new self($parameters, $this->members);
    }

    public function toHttpValue(): string
    {
        return '('.implode(' ', array_map(fn (Value $value): string => $value->toHttpValue(), $this->members)).')'.$this->parameters->toHttpValue();
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
     * @return Iterator<array-key, Value>
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

    public function get(string|int $offset): Value
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
     * Insert members at the beginning of the list.
     */
    public function unshift(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        $this->members = [...array_map(self::filterMember(...), array_values($members)), ...$this->members];

        return $this;
    }

    /**
     * Insert members at the end of the list.
     */
    public function push(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        $this->members = [...$this->members, ...array_map(self::filterMember(...), array_values($members))];

        return $this;
    }

    private static function filterMember(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): Value
    {
        return match (true) {
            $member instanceof Value => $member,
            $member instanceof StructuredField => throw new InvalidArgument('Expecting a "'.Value::class.'" instance; received a "'.$member::class.'" instead.'),
            default => Item::from($member),
        };
    }

    /**
     * Replace the member associated with the index.
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

    public function replace(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
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
    public function offsetGet($offset): Value
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
     * @param Value|DataType $value the member to add
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
