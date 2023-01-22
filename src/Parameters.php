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
use function is_string;
use function trim;

/**
 * @implements MemberOrderedMap<string, Item>
 */
final class Parameters implements MemberOrderedMap
{
    /** @var array<string, Item> */
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
     * @param iterable<array-key, Item|DataType> $members
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
     * @param MemberOrderedMap<string, Item>|iterable<array{0:string, 1:Item|DataType}> $pairs
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
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     *
     * @throws SyntaxError If the string is not a valid
     */
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        $httpValue = trim((string) $httpValue);
        [$parameters, $offset] = Parser::parseParameters($httpValue);
        if (strlen($httpValue) !== $offset) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for Parameters contains invalid characters.');
        }

        return self::fromAssociative($parameters);
    }

    public function toHttpValue(): string
    {
        $formatter = static fn (Item $member, string $offset): string => match (true) {
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
        return !$this->hasMembers();
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
     * Tells whether the key is present in the container.
     */
    public function has(string|int $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->members);
    }

    /**
     * Returns the Item associated to the key.
     *
     * @throws InvalidOffset if the key is not found
     */
    public function get(string|int $offset): Item
    {
        if (is_int($offset) || !array_key_exists($offset, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($offset);
        }

        return $this->members[$offset];
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
     * @throws InvalidOffset if the index is not found
     *
     * @return array{0:string, 1:Item}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);

        $i = 0;
        foreach ($this->members as $key => $member) {
            if ($i === $offset) {
                return [$key, $member];
            }
            ++$i;
        }

        // @codeCoverageIgnoreStart
        throw InvalidOffset::dueToIndexNotFound($index);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Adds a member at the end of the instance otherwise updates the value associated with the key if already present.
     */
    public function set(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
    {
        $this->members[MapKey::fromString($key)->value] = self::filterMember($member);

        return $this;
    }

    private static function filterMember(Item|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): Item
    {
        return match (true) {
            $member instanceof Item && $member->parameters()->hasNoMembers() => $member,
            !$member instanceof Item => Item::from($member),
            default => throw new InvalidArgument('Parameters instances can only contain bare items.'),
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
     */
    public function append(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): self
    {
        unset($this->members[$key]);

        return $this->set($key, $member);
    }

    /**
     * Adds a member at the beginning of the instance and deletes any previous reference to the key if present.
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
     * @param iterable<string, Item|DataType> ...$others
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
     * @param MemberOrderedMap<string, Item>|iterable<array{0:string, 1:Item|DataType}> ...$others
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
     * @param Item|DataType $value  the member value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            throw new SyntaxError('The offset for a Parameter member is expected to be a string; "'.gettype($offset).'" given.');
        }

        $this->set($offset, $value);
    }
}
