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
 * @implements MemberOrderedMap<string, Item>
 */
final class Parameters implements MemberOrderedMap
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
    private static function filterMember(Item $item, string|int $offset = null): Item
    {
        if (!$item->parameters->hasMembers()) {
            return $item;
        }

        $message = 'Parameters instances can only contain bare items.';
        if (null !== $offset) {
            $message = 'Parameter member `"'.$offset.'"` is in invalid state; '.$message;
        }

        throw new ForbiddenStateError($message);
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
     * @param MemberOrderedMap<string, Item>|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> $pairs
     *
     * @throws ForbiddenStateError If the bare item contains parameters
     */
    public static function fromPairs(MemberOrderedMap|iterable $pairs = []): self
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
        $formatter = fn (Item $member, string $offset): string => match (true) {
            $member->parameters->hasMembers() => throw new ForbiddenStateError('Parameter member `"'.$offset.'"` is in invalid state; Parameters instances can only contain bare items.'),
            true === $member->value() => ';'.$offset,
            default => ';'.$offset.'='.$member->toHttpValue(),
        };

        return implode('', array_map($formatter, $this->members, array_keys($this->members)));
    }

    public function count(): int
    {
        return count($this->members);
    }

    public function hasNoMembers(): bool
    {
        return [] === $this->members;
    }

    public function hasMembers(): bool
    {
        return !$this->hasNoMembers();
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
            yield [$index, self::filterMember($member, $index)];
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
     * @return array<string, float|int|bool|string>
     */
    public function values(): array
    {
        $result = [];
        foreach ($this->members as $offset => $item) {
            try {
                $result[$offset] = self::filterMember($item, $offset)->value();
            } catch (Throwable) {
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
    public function get(string|int $offset): Item
    {
        if (is_int($offset) || !array_key_exists($offset, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($offset);
        }

        return self::filterMember($this->members[$offset], $offset);
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
                return [$key, self::filterMember($member, $index)];
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
     * @param MemberOrderedMap<string, Item>|iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> ...$others
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
