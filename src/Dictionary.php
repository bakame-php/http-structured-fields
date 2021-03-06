<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_array;

/**
 * @implements IteratorAggregate<array-key, Item|InnerList>
 */
final class Dictionary implements Countable, IteratorAggregate, StructuredField
{
    /** @var array<string, Item|InnerList>  */
    private array $members = [];

    /**
     * @param iterable<string, InnerList|Item|ByteSequence|Token|bool|int|float|string> $members
     */
    private function __construct(iterable $members = [])
    {
        foreach ($members as $key => $member) {
            $this->set($key, $member);
        }
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<string, InnerList|Item|ByteSequence|Token|bool|int|float|string> $members
     */
    public static function fromAssociative(iterable $members = []): self
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
     * @param Dictionary|iterable<array{0:string, 1:InnerList|Item|ByteSequence|Token|bool|int|float|string}> $pairs
     */
    public static function fromPairs(Dictionary|iterable $pairs = []): self
    {
        if ($pairs instanceof Dictionary) {
            return clone $pairs;
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
    public static function fromHttpValue(string $httpValue): self
    {
        return self::fromAssociative(array_map(
            fn (mixed $value): mixed => is_array($value) ? InnerList::fromList(...$value) : $value,
            Parser::parseDictionary($httpValue)
        ));
    }

    public function toHttpValue(): string
    {
        $formatter = fn (Item|InnerList $member, string $key): string => match (true) {
            $member instanceof Item && true === $member->value => $key.$member->parameters->toHttpValue(),
            default => $key.'='.$member->toHttpValue(),
        };

        return implode(', ', array_map($formatter, $this->members, array_keys($this->members)));
    }

    public function count(): int
    {
        return count($this->members);
    }

    /**
     * Tells whether the instance contains no member.
     */
    public function isEmpty(): bool
    {
        return [] === $this->members;
    }

    /**
     * @return Iterator<string, Item|InnerList>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:string, 1:Item|InnerList}>
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
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->members);
    }

    /**
     * Returns the item or the inner-list is attached to the given key otherwise throw.
     *
     * @throws SyntaxError   If the key is invalid
     * @throws InvalidOffset If the key is not found
     */
    public function get(string $key): Item|InnerList
    {
        if (!array_key_exists($key, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->members[$key];
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
     * @return array{0:string, 1:Item|InnerList}
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
    public function set(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        $this->members[MapKey::fromString($key)->value] = self::filterMember($member);

        return $this;
    }

    private static function filterMember(InnerList|Item|ByteSequence|Token|bool|int|float|string $member): InnerList|Item
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

    /**
     * Removes all members from the instance.
     */
    public function clear(): self
    {
        $this->members = [];

        return $this;
    }

    /**
     * Ensure the container contains valid members.
     */
    public function sanitize(): self
    {
        foreach ($this->members as $member) {
            $member->sanitize();
        }

        return $this;
    }

    /**
     * Adds a member at the end of the instance and deletes any previous reference to the key if present.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function append(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): self
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
    public function prepend(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        unset($this->members[$key]);

        $this->members = [...[MapKey::fromString($key)->value => self::filterMember($member)], ...$this->members];

        return $this;
    }

    /**
     * Merges multiple instances using iterable associative structures.
     *
     * @param iterable<string, InnerList|Item|ByteSequence|Token|bool|int|float|string> ...$others
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
     * @param Dictionary|iterable<array{0:string, 1:InnerList|Item|ByteSequence|Token|bool|int|float|string}> ...$others
     */
    public function mergePairs(Dictionary|iterable ...$others): self
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromPairs($other)->members];
        }

        return $this;
    }
}
