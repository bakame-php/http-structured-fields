<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;
use Throwable;
use TypeError;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function is_string;
use function ltrim;
use function rtrim;
use function trim;

/**
 * @implements StructuredFieldOrderedMap<string, Item>
 */
final class Parameters implements StructuredFieldOrderedMap
{
    /** @var array<string, Item> */
    private array $members = [];

    /**
     * @param iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> $members
     */
    private function __construct(iterable $members = [])
    {
        foreach ($members as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @throws ForbiddenStateError If the bare item contains parameters
     * @throws TypeError If the structured field is not supported
     */
    private static function filterMember(Item $item): Item
    {
        if ($item->parameters->isNotEmpty()) {
            throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.');
        }

        return $item;
    }

    private static function formatMember(StructuredField|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => self::filterMember($member),
            $member instanceof StructuredField => throw new TypeError('Expecting a "'.Item::class.'" instance; received "'.$member::class.'" instead.'),
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
        return new self($members);
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each member is composed of an array with two elements
     * the first member represents the instance entry key
     * the second member represents the instance entry value
     *
     * @param StructuredFieldOrderedMap<string, Item>|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> $pairs
     *
     * @throws ForbiddenStateError If the bare item contains parameters
     */
    public static function fromPairs(StructuredFieldOrderedMap|iterable $pairs = []): self
    {
        if ($pairs instanceof StructuredFieldOrderedMap) {
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
        $formatter = fn (Item $member, string $key): string => match (true) {
            !$member->parameters->isEmpty() => throw new ForbiddenStateError('Parameters instances can not contain parameterized Items.'),
            true === $member->value => ';'.$key,
            default => ';'.$key.'='.$member->toHttpValue(),
        };

        return implode('', array_map($formatter, $this->members, array_keys($this->members)));
    }

    public function count(): int
    {
        return count($this->members);
    }

    public function isEmpty(): bool
    {
        return [] === $this->members;
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @throws ForbiddenStateError if the bare item contains parameters itself
     *
     * @return Iterator<array-key, Item>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
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
     * Returns the Item value of a specific key if it exists and is valid otherwise returns null.
     */
    public function value(string|int $offset): Token|ByteSequence|float|int|bool|string|null
    {
        try {
            $member = $this->get($offset);
        } catch (Throwable) {
            return null;
        }

        return $member->value;
    }

    /**
     * Tells whether the key is present in the container.
     */
    public function has(string|int $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->members);
    }

    /**
     * Returns the Item associated to the key.
     *
     * @throws InvalidOffset       if the key is not found
     * @throws ForbiddenStateError if the bare item contains parameters itself
     */
    public function get(string|int $key): Item
    {
        if (is_int($key) || !array_key_exists($key, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return self::filterMember($this->members[$key]);
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
    public function set(string $key, StructuredField|ByteSequence|Token|bool|int|float|string $member): self
    {
        $this->members[MapKey::fromString($key)->value] = self::formatMember($member);

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
    public function append(string $key, StructuredField|ByteSequence|Token|bool|int|float|string $member): self
    {
        unset($this->members[$key]);

        $this->members[MapKey::fromString($key)->value] = self::formatMember($member);

        return $this;
    }

    /**
     * Adds a member at the beginning of the instance and deletes any previous reference to the key if present.
     *
     * @throws SyntaxError         If the string key is not a valid
     * @throws ForbiddenStateError if the found item is in invalid state
     */
    public function prepend(string $key, StructuredField|ByteSequence|Token|bool|int|float|string $member): self
    {
        unset($this->members[$key]);

        $this->members = [...[MapKey::fromString($key)->value =>  self::formatMember($member)], ...$this->members];

        return $this;
    }

    /**
     * Merges multiple instances using iterable associative structures.
     *
     * @param iterable<string, Item|Token|ByteSequence|float|int|bool|string> ...$others
     */
    public function mergeAssociative(iterable ...$others): self
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromAssociative($other)->members];
        }

        return $this;
    }

    /**
     * Merge multiple instances using iterable pairs.
     *
     * @param StructuredFieldOrderedMap<string, Item>|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> ...$others
     */
    public function mergePairs(StructuredFieldOrderedMap|iterable ...$others): self
    {
        foreach ($others as $other) {
            $this->members = [...$this->members, ...self::fromPairs($other)->members];
        }

        return $this;
    }

    /**
     * Ensure the container always contains only Bare Items.
     *
     * If Item with parameters exists they will be strip from the object
     * before returning the parent instance
     */
    public function sanitize(): self
    {
        foreach ($this->members as $item) {
            $item->parameters->clear();
        }

        return $this;
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param string $offset
     */
    public function offsetGet($offset): Item
    {
        return $this->get($offset);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        $this->delete($offset);
    }

    /**
     * @param string|null $offset
     * @param Item|ByteSequence|Token|bool|int|float|string $value  the member value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            throw new SyntaxError('The offset for a parameter member is expected to be a string; "'.gettype($offset).'" given.');
        }

        $this->set($offset, $value);
    }
}
