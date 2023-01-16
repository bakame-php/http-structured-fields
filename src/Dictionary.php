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
 * @implements MemberOrderedMap<string, Item|InnerList<int, Item>>
 */
final class Dictionary implements MemberOrderedMap
{
    /** @var array<string, Item|InnerList<int, Item>> */
    private array $members = [];

    private function __construct()
    {
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
     * @param iterable<string, InnerList<int, Item>|Item|DataType> $members
     */
    public static function fromAssociative(iterable $members): self
    {
        $instance = new self();
        foreach ($members as $key => $member) {
            $instance->set($key, $member);
        }

        return $instance;
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each member is composed of an array with two elements
     * the first member represents the instance entry key
     * the second member represents the instance entry value
     *
     * @param MemberOrderedMap<string, Item|InnerList<int, Item>>|iterable<array{0:string, 1:InnerList<int, Item>|Item|DataType}> $pairs
     */
    public static function fromPairs(MemberOrderedMap|iterable $pairs): self
    {
        if ($pairs instanceof MemberOrderedMap) {
            $pairs = $pairs->toPairs();
        }

        $instance = new self();
        foreach ($pairs as [$key, $member]) {
            $instance->set($key, $member);
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
        $formatter = fn (Item|InnerList $member, string $key): string => match (true) {
            $member instanceof Item && true === $member->value() => $key.$member->parameters()->toHttpValue(),
            default => $key.'='.$member->toHttpValue(),
        };

        return implode(', ', array_map($formatter, $this->members, array_keys($this->members)));
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
     * @return Iterator<string, Item|InnerList<int, Item>>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:string, 1:Item|InnerList<int, Item>}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, $member];
        }
    }

    /**
     * Returns an ordered list of the instance keys.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    /**
     * Tells whether an item or an inner-list is attached to the given key.
     */
    public function has(string|int $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->members);
    }

    /**
     * Returns the item or the inner-list is attached to the given key otherwise throw.
     *
     * @throws SyntaxError   If the key is invalid
     * @throws InvalidOffset If the key is not found
     */
    public function get(string|int $offset): Item|InnerList
    {
        if (is_int($offset) || !array_key_exists($offset, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($offset);
        }

        return $this->members[$offset];
    }

    /**
     * Tells whether an item or an inner-list and a key are attached to the given index position.
     */
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
     * Returns the item or the inner-list and its key as attached to the given
     * collection according to their index position otherwise throw.
     *
     * @throws InvalidOffset If the key is not found
     *
     * @return array{0:string, 1:Item|InnerList<int, Item>}
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
     * Adds a member at the end of the instance otherwise updates the value associated with the key if already present.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function set(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
    {
        $this->members[MapKey::fromString($key)->value] = self::filterMember($member);

        return $this;
    }

    private static function filterMember(InnerList|Item|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): InnerList|Item
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    /**
     * Deletes members associated with the list of submitted keys.
     */
    public function delete(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->members[$key]);
        }

        return $this;
    }

    public function clear(): self
    {
        $this->members = [];

        return $this;
    }

    /**
     * Adds a member at the end of the instance and deletes any previous reference to the key if present.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function append(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
    {
        unset($this->members[$key]);

        $this->members[MapKey::fromString($key)->value] = self::filterMember($member);

        return $this;
    }

    /**
     * Adds a member at the beginning of the instance and deletes any previous reference to the key if present.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function prepend(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
    {
        unset($this->members[$key]);

        $this->members = [...[MapKey::fromString($key)->value => self::filterMember($member)], ...$this->members];

        return $this;
    }

    /**
     * Merges multiple instances using iterable associative structures.
     *
     * @param iterable<string, InnerList<int, Item>|Item|DataType> ...$others
     */
    public function mergeAssociative(iterable ...$others): self
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromAssociative($other)->members];
        }

        return $this;
    }

    /**
     * Merges multiple instances using iterable pairs.
     *
     * @param MemberOrderedMap<string, Item|InnerList<int, Item>>|iterable<array{0:string, 1:InnerList<int, Item>|Item|DataType}> ...$others
     */
    public function mergePairs(MemberOrderedMap|iterable ...$others): self
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
     * @return Item|InnerList<int, Item>
     */
    public function offsetGet(mixed $offset): InnerList|Item
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
     * @param InnerList<int, Item>|Item|DataType $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            throw new SyntaxError('The offset for a dictionary member is expected to be a string; "'.gettype($offset).'" given.');
        }

        $this->set($offset, $value);
    }
}
