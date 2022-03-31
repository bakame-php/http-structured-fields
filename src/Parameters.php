<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;
use function array_key_exists;
use function array_keys;
use function count;
use function explode;
use function ltrim;
use function preg_match;
use function rtrim;
use function trim;

/**
 * @implements IteratorAggregate<string, Item>
 */
final class Parameters implements Countable, IteratorAggregate, StructuredField
{
    private function __construct(
        /** @var array<string, Item> */
        private array $members = []
    ) {
    }

    /**
     * @param array{members:array<string, Item>} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['members']);
    }

    /**
     * @throws SyntaxError If the string is not a valid
     */
    private static function filterKey(string $key): string
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError("The Parameters key `$key` contains invalid characters.");
        }

        return $key;
    }

    /**
     * @throws ForbiddenStateError If the bare item contains parameters
     */
    private static function filterMember(Item $item): Item
    {
        if (!$item->parameters->isEmpty()) {
            throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.');
        }

        return $item;
    }

    private static function formatMember(Item|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => self::filterMember($member),
            default => Item::from($member),
        };
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> $members
     *
     * @throws SyntaxError         If the string is not a valid
     * @throws ForbiddenStateError If the bare item contains parameters
     */
    public static function fromAssociative(iterable $members = []): self
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
     * @param Parameters|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> $pairs
     *
     * @throws ForbiddenStateError If the bare item contains parameters
     */
    public static function fromPairs(Parameters|iterable $pairs = []): self
    {
        if ($pairs instanceof Parameters) {
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
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     *
     * @throws SyntaxError         If the string is not a valid
     * @throws ForbiddenStateError If the bare item contains parameters
     */
    public static function fromHttpValue(string $httpValue): self
    {
        $instance = new self();
        $httpValue = ltrim($httpValue, ' ');
        if ('' === $httpValue) {
            return $instance;
        }

        foreach (explode(';', $httpValue) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => '?1'];
            if (rtrim($key) !== $key || ltrim($value) !== $value) {
                throw new SyntaxError("The HTTP textual representation `$pair` for a parameter pair contains invalid characters.");
            }

            $key = trim($key);
            if ('' !== $key) {
                $instance->set($key, Item::fromHttpValue($value));
            }
        }

        return $instance;
    }

    /**
     * @throws ForbiddenStateError if the bare item contains parameters itself
     */
    public function toHttpValue(): string
    {
        $returnValue = [];

        foreach ($this->members as $key => $member) {
            $value = ';'.$key;
            $member = self::filterMember($member);

            if ($member->value !== true) {
                $value .= '='.$member->toHttpValue();
            }

            $returnValue[] = $value;
        }

        return implode('', $returnValue);
    }

    public function count(): int
    {
        return count($this->members);
    }

    /**
     * Tells whether the container is empty or not.
     */
    public function isEmpty(): bool
    {
        return [] === $this->members;
    }

    /**
     * @throws ForbiddenStateError if the bare item contains parameters itself
     *
     * @return Iterator<string, Item>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $key => $member) {
            yield $key => self::filterMember($member);
        }
    }

    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @throws ForbiddenStateError if the bare item contains parameters itself
     *
     * @return Iterator<array{0:string, 1:Item}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, self::filterMember($member)];
        }
    }

    /**
     * Returns all the container keys.
     *
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    /**
     * Returns all containers Item values.
     *
     * @throws ForbiddenStateError if the bare item contains parameters itself
     *
     * @return array<string, Token|ByteSequence|float|int|bool|string>
     */
    public function values(): array
    {
        return array_map(fn (Item $item): Token|ByteSequence|float|int|bool|string => self::filterMember($item)->value, $this->members);
    }

    /**
     * Tells whether the key is present in the container.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->members);
    }

    /**
     * Returns the Item associated to the key.
     *
     * @throws InvalidOffset       if the key is not found
     * @throws ForbiddenStateError if the bare item contains parameters itself
     */
    public function get(string $key): Item
    {
        if (!array_key_exists($key, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return self::filterMember($this->members[$key]);
    }

    /**
     * Returns the Item value of a specific key if it exists and is valid otherwise returns null.
     *
     * @throws ForbiddenStateError if the found item is in invalid state
     */
    public function value(string $key): Token|ByteSequence|float|int|bool|string|null
    {
        if (!array_key_exists($key, $this->members)) {
            return null;
        }

        return self::filterMember($this->members[$key])->value;
    }

    /**
     * Tells whether the index is present in the container.
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
     * Filters and format instance index.
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
     * Returns the key-item pair found at a given index.
     *
     * @throws InvalidOffset       if the index is not found
     * @throws ForbiddenStateError if the found item is in invalid state
     *
     * @return array{0:string, 1:Item}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);

        $i = 0;
        foreach ($this->members as $key => $member) {
            if ($i === $offset) {
                return [$key, self::filterMember($member)];
            }
            ++$i;
        }

        // @codeCoverageIgnoreStart
        throw InvalidOffset::dueToIndexNotFound($index);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Adds a member at the end of the instance otherwise updates the value associated with the key if already present.
     *
     * @throws SyntaxError         If the string key is not a valid
     * @throws ForbiddenStateError if the found item is in invalid state
     */
    public function set(string $key, Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        $this->members[self::filterKey($key)] = self::formatMember($member);

        return $this;
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
     * Adds a member at the end of the instance and deletes any previous reference to the key if present.
     *
     * @throws SyntaxError         If the string key is not a valid
     * @throws ForbiddenStateError if the found item is in invalid state
     */
    public function append(string $key, Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        unset($this->members[$key]);

        $this->members[self::filterKey($key)] = self::formatMember($member);

        return $this;
    }

    /**
     * Adds a member at the beginning of the instance and deletes any previous reference to the key if present.
     *
     * @throws SyntaxError         If the string key is not a valid
     * @throws ForbiddenStateError if the found item is in invalid state
     */
    public function prepend(string $key, Item|ByteSequence|Token|bool|int|float|string $member): self
    {
        unset($this->members[$key]);

        $this->members = [...[self::filterKey($key) =>  self::formatMember($member)], ...$this->members];

        return $this;
    }

    /**
     * Merges multiple instances using iterable associative structures.
     *
     * @param  Parameters|iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> ...$others
     * @throws ForbiddenStateError                                                           if the found item is in invalid state
     */
    public function mergeAssociative(Parameters|iterable ...$others): self
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromAssociative($other)->members];
        }

        return $this;
    }

    /**
     * Merge multiple instances using iterable pairs.
     *
     * @param Parameters|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> ...$others
     */
    public function mergePairs(Parameters|iterable ...$others): self
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromPairs($other)->members];
        }

        return $this;
    }
}
