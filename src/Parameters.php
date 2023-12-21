<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use DateTimeInterface;
use Iterator;
use Stringable;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_int;
use function is_string;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
 *
 * @phpstan-import-type SfItem from StructuredField
 * @phpstan-import-type SfItemInput from StructuredField
 *
 * @implements MemberOrderedMap<string, SfItem>
 */
final class Parameters implements MemberOrderedMap
{
    /** @var array<string, SfItem> */
    private readonly array $members;

    /**
     * @param iterable<string, SfItemInput> $members
     */
    private function __construct(iterable $members = [])
    {
        $filteredMembers = [];
        foreach ($members as $key => $member) {
            $filteredMembers[MapKey::from($key)->value] = self::filterMember($member);
        }

        $this->members = $filteredMembers;
    }

    /**
     * @param SfItemInput $member
     *
     * @return SfItem
     */
    private static function filterMember(mixed $member): object
    {
        return match (true) {
            $member instanceof ParameterAccess && $member instanceof ValueAccess => $member->parameters()->hasNoMembers() ? $member : throw new InvalidArgument('The "'.$member::class.'" instance is not a Bare Item.'),
            $member instanceof StructuredField => throw new InvalidArgument('An instance of "'.$member::class.'" can not be a member of "'.self::class.'".'),
            default => Item::new($member),
        };
    }

    /**
     * Returns a new instance.
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<string, SfItemInput> $members
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
     * @param MemberOrderedMap<string, SfItem>|iterable<array{0:string, 1:SfItemInput}> $pairs
     */
    public static function fromPairs(iterable $pairs): self
    {
        return match (true) {
            $pairs instanceof MemberOrderedMap => new self($pairs),
            default => new self((function (iterable $pairs) {
                foreach ($pairs as [$key, $member]) {
                    yield $key => $member;
                }
            })($pairs)),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     *
     * @throws SyntaxError If the string is not a valid
     */
    public static function fromHttpValue(Stringable|string $httpValue, ParametersParser $parser = new Parser()): self
    {
        return new self($parser->parseParameters($httpValue));
    }

    public function toHttpValue(): string
    {
        $formatter = static fn (ValueAccess $member, string $offset): string => match (true) {
            true === $member->value() => ';'.$offset,
            default => ';'.$offset.'='.$member->toHttpValue(),
        };

        return implode('', array_map($formatter, $this->members, array_keys($this->members)));
    }

    public function __toString(): string
    {
        return $this->toHttpValue();
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

    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    /**
     * @return Iterator<int, array{0:string, 1:SfItem}>
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

    public function has(string|int ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!is_string($key) || !array_key_exists($key, $this->members)) {
                return false;
            }
        }

        return [] !== $keys;
    }

    /**
     * @throws InvalidOffset If the key is not found
     *
     * @return SfItem
     */
    public function get(string|int $key): StructuredField
    {
        return $this->members[$key] ?? throw InvalidOffset::dueToKeyNotFound($key);
    }

    public function hasPair(int ...$indexes): bool
    {
        $max = count($this->members);
        foreach ($indexes as $index) {
            if (null === $this->filterIndex($index, $max)) {
                return false;
            }
        }

        return [] !== $indexes;
    }

    /**
     * Filters and format instance index.
     */
    private function filterIndex(int $index, int|null $max = null): int|null
    {
        $max ??= count($this->members);

        return match (true) {
            [] === $this->members,
            0 > $max + $index,
            0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    /**
     * @throws InvalidOffset If the key is not found
     *
     * @return array{0:string, 1:SfItem}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);

        return [...$this->toPairs()][$offset];
    }

    public function add(string $key, StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member): static
    {
        $members = $this->members;
        $members[MapKey::from($key)->value] = self::filterMember($member);

        return $this->newInstance($members);
    }

    /**
     * @param array<string, SfItem> $members
     */
    private function newInstance(array $members): self
    {
        return match(true) {
            $members == $this->members => $this,
            default => new self($members),
        };
    }

    public function remove(string|int ...$keys): static
    {
        if ([] === $this->members || [] === $keys) {
            return $this;
        }

        $offsets = array_keys($this->members);
        $max = count($offsets);
        $reducer = fn (array $carry, string|int $key): array => match (true) {
            is_string($key) && (false !== ($position = array_search($key, $offsets, true))),
            is_int($key) && (null !== ($position = $this->filterIndex($key, $max))) => [$position => true] + $carry,
            default => $carry,
        };

        $indices = array_reduce($keys, $reducer, []);

        return match (true) {
            [] === $indices => $this,
            $max === count($indices) => self::new(),
            default => self::fromPairs((function (array $offsets) {
                foreach ($this->toPairs() as $offset => $pair) {
                    if (!array_key_exists($offset, $offsets)) {
                        yield $pair;
                    }
                }
            })($indices)),
        };
    }

    public function removeByIndices(int ...$indices): static
    {
        return $this->remove(...$indices);
    }

    public function removeByKeys(string ...$keys): static
    {
        return $this->remove(...$keys);
    }

    public function append(
        string $key,
        StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        $members = $this->members;
        unset($members[$key]);

        return $this->newInstance([...$members, MapKey::from($key)->value => self::filterMember($member)]);
    }

    public function prepend(
        string $key,
        StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        $members = $this->members;
        unset($members[$key]);

        return $this->newInstance([MapKey::from($key)->value => self::filterMember($member), ...$members]);
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function push(array ...$pairs): self
    {
        return match (true) {
            [] === $pairs => $this,
            default => self::fromPairs((function (iterable $pairs) {
                yield from $this->toPairs();
                yield from $pairs;
            })($pairs)),
        };
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function unshift(array ...$pairs): self
    {
        return match (true) {
            [] === $pairs => $this,
            default => self::fromPairs((function (iterable $pairs) {
                yield from $pairs;
                yield from $this->toPairs();
            })($pairs)),
        };
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$members
     */
    public function insert(int $index, array ...$members): static
    {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);

        return match (true) {
            [] === $members => $this,
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            default => (function (Iterator $newMembers) use ($offset, $members) {
                $newMembers = iterator_to_array($newMembers);
                array_splice($newMembers, $offset, 0, $members);

                return self::fromPairs($newMembers);
            })($this->toPairs()),
        };
    }

    /**
     * @param array{0:string, 1:SfItemInput} $pair
     */
    public function replace(int $index, array $pair): static
    {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);
        $pair[1] = self::filterMember($pair[1]);
        $pairs = iterator_to_array($this->toPairs());

        return match (true) {
            $pairs[$offset] == $pair => $this,
            default => self::fromPairs(array_replace($pairs, [$offset => $pair])),
        };
    }

    /**
     * @param iterable<string, SfItemInput> ...$others
     */
    public function mergeAssociative(iterable ...$others): static
    {
        $members = $this->members;
        foreach ($others as $other) {
            $members = [...$members, ...self::fromAssociative($other)->members];
        }

        return new self($members);
    }

    /**
     * @param MemberOrderedMap<string, SfItem>|iterable<array{0:string, 1:SfItemInput}> ...$others
     */
    public function mergePairs(MemberOrderedMap|iterable ...$others): static
    {
        $members = $this->members;
        foreach ($others as $other) {
            $members = [...$members, ...self::fromPairs($other)->members];
        }

        return new self($members);
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
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }
}
