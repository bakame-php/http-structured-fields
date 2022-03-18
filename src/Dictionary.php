<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;
use function array_key_exists;
use function array_keys;
use function array_values;
use function count;
use function implode;
use function preg_match;

/**
 * @implements IteratorAggregate<array-key, Item|InnerList>
 */
final class Dictionary implements Countable, IteratorAggregate, StructuredField
{
    private function __construct(
        /** @var array<string, Item|InnerList>  */
        private array $members = []
    ) {
    }

    /**
     * @param array{members:array<string, Item|InnerList>} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['members']);
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
        $instance = new self();
        foreach ($members as $index => $member) {
            $instance->set($index, $member);
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
     * @param iterable<array{0:string, 1:InnerList|Item|ByteSequence|Token|bool|int|float|string}> $pairs
     */
    public static function fromPairs(iterable $pairs = []): self
    {
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
        return self::fromAssociative(Parser::parseDictionary($httpValue));
    }

    public function toHttpValue(): string
    {
        $returnValue = [];
        foreach ($this->members as $key => $member) {
            $returnValue[] = match (true) {
                $member instanceof Item && true === $member->value() => $key.$member->parameters()->toHttpValue(),
                default => $key.'='.$member->toHttpValue(),
            };
        }

        return implode(', ', $returnValue);
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
     * @return Iterator<string, Item|InnerList>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield $index => $member;
        }
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
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->members);
    }

    public function get(string $key): Item|InnerList
    {
        self::validateKey($key);

        if (!array_key_exists($key, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->members[$key];
    }

    public function hasPair(int $index): bool
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

    /**
     * @return array{0:string, 1:Item|InnerList}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return [
            array_keys($this->members)[$offset],
            array_values($this->members)[$offset],
        ];
    }

    /**
     * Add a member at the end of the instance if the key is new otherwise update the value associated with the key.
     */
    public function set(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        self::validateKey($key);

        $this->members[$key] = self::filterMember($member);
    }

    /**
     * Validate the instance key against RFC8941 rules.
     */
    private static function validateKey(string $key): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError("The dictionary key `$key` contains invalid characters.");
        }
    }

    private static function filterMember(InnerList|Item|ByteSequence|Token|bool|int|float|string $member): InnerList|Item
    {
        return match (true) {
            $member instanceof InnerList, $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    /**
     * Delete members associated with the list of submitted keys.
     */
    public function delete(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->members[$key]);
        }
    }

    /**
     * Remove all members from the instance.
     */
    public function clear(): void
    {
        $this->members = [];
    }

    /**
     * Add a member at the end of the instance if the key is new delete any previous reference to the key.
     */
    public function append(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        self::validateKey($key);

        unset($this->members[$key]);

        $this->members[$key] = self::filterMember($member);
    }

    /**
     * Add a member at the beginning of the instance if the key is new delete any previous reference to the key.
     */
    public function prepend(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        self::validateKey($key);

        unset($this->members[$key]);

        $this->members = [...[$key => self::filterMember($member)], ...$this->members];
    }

    /**
     * Merge multiple instances.
     */
    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...$other->members];
        }
    }
}
