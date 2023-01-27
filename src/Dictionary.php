<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;
use Iterator;
use Stringable;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_array;

/**
 * @implements MemberOrderedMap<string, Value|InnerList<int, Value>>
 * @phpstan-import-type DataType from Item
 */
final class Dictionary implements MemberOrderedMap
{
    /** @var array<string, Value|InnerList<int, Value>> */
    private array $members = [];

    /**
     * @param iterable<string, InnerList<int, Value>|Value|DataType> $members
     */
    private function __construct(iterable $members = [])
    {
        foreach ($members as $key => $member) {
            $this->set($key, $member);
        }
    }

    /**
     * Returns a new instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<string, InnerList<int, Value>|Value|DataType> $members
     */
    public static function fromAssociative(iterable $members): self
    {
        return new self($members);
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each member is composed of an array with two elements
     * the first member represents the instance entry key
     * the second member represents the instance entry value
     *
     * @param MemberOrderedMap<string, Value|InnerList<int, Value>>|iterable<array{0:string, 1:InnerList<int, Value>|Value|DataType}> $pairs
     */
    public static function fromPairs(MemberOrderedMap|iterable $pairs): self
    {
        if ($pairs instanceof MemberOrderedMap) {
            $pairs = $pairs->toPairs();
        }

        $instance = new self();
        foreach ($pairs as $pair) {
            $instance->set(...$pair);
        }

        return $instance;
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.2
     */
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        $instance = new self();
        foreach (Parser::parseDictionary($httpValue) as $key => $value) {
            $instance->set($key, is_array($value) ? InnerList::fromList(...$value) : $value);
        }

        return $instance;
    }

    public function toHttpValue(): string
    {
        $formatter = static fn (Value|InnerList $member, string $key): string => match (true) {
            $member instanceof Value && true === $member->value() => $key.$member->parameters()->toHttpValue(),
            default => $key.'='.$member->toHttpValue(),
        };

        return implode(', ', array_map($formatter, $this->members, array_keys($this->members)));
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
     * @return Iterator<string, Value|InnerList<int, Value>>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    /**
     * @return Iterator<array{0:string, 1:Value|InnerList<int, Value>}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, $member];
        }
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function has(string|int $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->members);
    }

    /**
     * @throws SyntaxError   If the key is invalid
     * @throws InvalidOffset If the key is not found
     */
    public function get(string|int $offset): Value|InnerList
    {
        if (is_int($offset) || !array_key_exists($offset, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($offset);
        }

        return $this->members[$offset];
    }

    public function hasPair(int $index): bool
    {
        try {
            $this->filterIndex($index);

            return true;
        } catch (InvalidOffset) {
            return false;
        }
    }

    /**
     * Validates and Format the submitted index position.
     */
    private function filterIndex(int $index): int
    {
        $max = count($this->members);

        return match (true) {
            [] === $this->members, 0 > $max + $index, 0 > $max - $index - 1 => throw InvalidOffset::dueToIndexNotFound($index),
            0 > $index => $max + $index,
            default => $index,
        };
    }

    /**
     * @throws InvalidOffset If the key is not found
     *
     * @return array{0:string, 1:Value|InnerList<int, Value>}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);

        foreach ($this->toPairs() as $k => $pair) {
            if ($k === $offset) {
                return $pair;
            }
        }

        // @codeCoverageIgnoreStart
        throw InvalidOffset::dueToIndexNotFound($index);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws SyntaxError If the string key is not a valid
     */
    public function set(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        $this->members[MapKey::fromString($key)->value] = self::filterMember($member);

        return $this;
    }

    private static function filterMember(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): InnerList|Value
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Value => $member,
            $member instanceof StructuredField => throw new InvalidArgument('Expecting a "'.Value::class.'" or a "'.InnerList::class.'" instance; received a "'.$member::class.'" instead.'),
            default => Item::from($member),
        };
    }

    public function delete(string ...$keys): static
    {
        foreach ($keys as $key) {
            unset($this->members[$key]);
        }

        return $this;
    }

    public function clear(): static
    {
        $this->members = [];

        return $this;
    }

    public function append(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        unset($this->members[$key]);

        return $this->set($key, $member);
    }

    public function prepend(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        unset($this->members[$key]);

        $this->members = [...[MapKey::fromString($key)->value => self::filterMember($member)], ...$this->members];

        return $this;
    }

    /**
     * @param iterable<string, InnerList<int, Value>|Value|DataType> ...$others
     */
    public function mergeAssociative(iterable ...$others): static
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromAssociative($other)->members];
        }

        return $this;
    }

    /**
     * @param MemberOrderedMap<string, Value|InnerList<int, Value>>|iterable<array{0:string, 1:InnerList<int, Value>|Value|DataType}> ...$others
     */
    public function mergePairs(MemberOrderedMap|iterable ...$others): static
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromPairs($other)->members];
        }

        return $this;
    }

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param string $offset
     *
     * @return Value|InnerList<int, Value>
     */
    public function offsetGet(mixed $offset): InnerList|Value
    {
        return $this->get($offset);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->delete($offset);
    }

    /**
     * @param InnerList<int, Value>|Value|DataType $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            throw new SyntaxError('The offset for a dictionary member is expected to be a string; "'.gettype($offset).'" given.');
        }

        $this->set($offset, $value);
    }
}
