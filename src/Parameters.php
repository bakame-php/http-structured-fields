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
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> $members
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
     * @param iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> $pairs
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
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     */
    public static function fromHttpValue(string $httpValue): self
    {
        $instance = new self();
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

    public function toHttpValue(): string
    {
        $returnValue = [];

        foreach ($this->members as $key => $val) {
            if (!$val->parameters->isEmpty()) {
                throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.');
            }

            $value = ';'.$key;
            if ($val->value !== true) {
                $value .= '='.$val->toHttpValue();
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
     * @return Iterator<string, Item>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:string, 1:Item}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, $member];
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
     * @return array<string, Token|ByteSequence|float|int|bool|string>
     */
    public function values(): array
    {
        return array_map(fn (Item $item): Token|ByteSequence|float|int|bool|string => $item->value, $this->members);
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
     * @throws SyntaxError         if the key is invalid
     * @throws InvalidOffset       if the key is not found
     * @throws ForbiddenStateError if the found item is in invalid state
     */
    public function get(string $key): Item
    {
        self::validateKey($key);

        if (!array_key_exists($key, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        $item = $this->members[$key];
        if (!$item->parameters->isEmpty()) {
            throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.');
        }

        return $item;
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

        $item = $this->members[$key];
        if (!$item->parameters->isEmpty()) {
            throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.');
        }

        return $item->value;
    }

    /**
     * Tells whether the index is present in the container.
     */
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
     * Returns the key-item pair positionned at a given index.
     *
     * @throws InvalidOffset       if the index is not found
     * @throws ForbiddenStateError if the found item is in invalid state
     *
     * @return array{0:string, 1:Item}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $item = array_values($this->members)[$offset];
        if (!$item->parameters->isEmpty()) {
            throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.');
        }

        return [array_keys($this->members)[$offset], $item];
    }

    /**
     * Add a member at the end of the instance if the key is new otherwise update the value associated with the key.
     */
    public function set(string $key, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        $member = self::filterMember($member);
        self::validate($key, $member);

        $this->members[$key] = $member;
    }

    private static function filterMember(Item|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    private static function validateKey(string $key): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError("The Parameters key `$key` contains invalid characters.");
        }
    }

    private static function validate(string $key, Item $item): void
    {
        self::validateKey($key);

        if (!$item->parameters->isEmpty()) {
            throw new SyntaxError('Parameters instances can not contain parameterized Items.');
        }
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
    public function append(string $key, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        $member = self::filterMember($member);
        self::validate($key, $member);

        unset($this->members[$key]);

        $this->members[$key] = $member;
    }

    /**
     * Add a member at the beginning of the instance if the key is new delete any previous reference to the key.
     */
    public function prepend(string $key, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        $member = self::filterMember($member);
        self::validate($key, $member);

        unset($this->members[$key]);

        $this->members = [...[$key => $member], ...$this->members];
    }

    /**
     * Merge multiple instances.
     *
     * iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> ...$others
     */
    public function merge(iterable ...$others): void
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromAssociative($other)->members];
        }
    }
}
