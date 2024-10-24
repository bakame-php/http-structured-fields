<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Closure;
use DateTimeInterface;
use Iterator;
use Stringable;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_array;
use function is_int;
use function is_iterable;
use function is_string;

use const ARRAY_FILTER_USE_BOTH;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.2
 *
 * @phpstan-import-type SfMember from StructuredField
 * @phpstan-import-type SfMemberInput from StructuredField
 *
 * @implements MemberOrderedMap<string, SfMember>
 */
final class Dictionary implements MemberOrderedMap
{
    /** @var array<string, SfMember> */
    private readonly array $members;

    /**
     * @param iterable<string, SfMember|SfMemberInput> $members
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
     * @param SfMember|SfMemberInput $member
     *
     * @return SfMember
     */
    private static function filterMember(mixed $member): object
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
        }

        return match (true) {
            $member instanceof ParameterAccess && ($member instanceof MemberList || $member instanceof ValueAccess) => $member,
            $member instanceof StructuredField => throw new InvalidArgument('An instance of "'.$member::class.'" can not be a member of "'.self::class.'".'),
            is_iterable($member) => InnerList::new(...$member),
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
     * @param iterable<string, SfMember|SfMemberInput> $members
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
     * @param MemberOrderedMap<string, SfMember>|iterable<array{0:string, 1:SfMember|SfMemberInput}> $pairs
     */
    public static function fromPairs(iterable $pairs): self
    {
        /**
         * @return ParameterAccess&(MemberList|ValueAccess)
         */
        $converter = function (mixed $pair): StructuredField {
            if ($pair instanceof StructuredFieldProvider) {
                $pair = $pair->toStructuredField();
            }

            if ($pair instanceof ParameterAccess && ($pair instanceof MemberList || $pair instanceof ValueAccess)) {
                return $pair;
            }

            if (!is_array($pair)) {
                return Item::new($pair); /* @phpstan-ignore-line */
            }

            if (!array_is_list($pair)) {
                throw new SyntaxError('The pair must be represented by an array as a list.');
            }

            if (2 !== count($pair)) {
                throw new SyntaxError('The pair first member is the item value; its second member is the item parameters.');
            }

            [$member, $parameters] = $pair;

            return is_iterable($member) ? InnerList::fromPair([$member, $parameters]) : Item::fromPair([$member, $parameters]);
        };

        return match (true) {
            $pairs instanceof MemberOrderedMap => new self($pairs),
            default => new self((function (iterable $pairs) use ($converter) {
                foreach ($pairs as [$key, $member]) {
                    yield $key => $converter($member);
                }
            })($pairs)),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.2
     *
     * @throws SyntaxError If the string is not a valid
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): self
    {
        $converter = fn (array $member): InnerList|Item => match (true) {
            is_array($member[0]) => InnerList::fromAssociative(
                array_map(fn (array $item) => Item::fromAssociative(...$item), $member[0]),
                $member[1]
            ),
            default => Item::fromAssociative(...$member),
        };

        return new self(array_map($converter, Parser::new($rfc)->parseDictionary($httpValue)));
    }

    public static function fromRfc9651(Stringable|string $httpValue): self
    {
        return self::fromHttpValue($httpValue, Ietf::Rfc9651);
    }

    public static function fromRfc8941(Stringable|string $httpValue): self
    {
        return self::fromHttpValue($httpValue, Ietf::Rfc8941);
    }

    public function toHttpValue(?Ietf $rfc = null): string
    {
        $rfc ??= Ietf::Rfc9651;
        $members = [];
        foreach ($this->members as $key => $member) {
            $members[] = match (true) {
                $member instanceof ValueAccess && true === $member->value() => $key.$member->parameters()->toHttpValue($rfc),
                default => $key.'='.$member->toHttpValue($rfc),
            };
        }

        return implode(', ', $members);
    }

    public function toRfc9651(): string
    {
        return $this->toHttpValue(Ietf::Rfc9651);
    }

    public function toRfc8941(): string
    {
        return $this->toHttpValue(Ietf::Rfc8941);
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
     * @return Iterator<int, array{0:string, 1:SfMember}>
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
     * @throws SyntaxError If the key is invalid
     * @throws InvalidOffset If the key is not found
     *
     * @return SfMember
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
     * @return array{0:string, 1:SfMember}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);

        return [...$this->toPairs()][$offset];
    }

    /**
     * @return ?array{0:string, 1:SfMember}
     */
    public function first(): ?array
    {
        try {
            return $this->pair(0);
        } catch (InvalidOffset) {
            return null;
        }
    }

    /**
     * @return ?array{0:string, 1:SfMember}
     */
    public function last(): ?array
    {
        try {
            return $this->pair(-1);
        } catch (InvalidOffset) {
            return null;
        }
    }

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function add(string $key, iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member): static
    {
        $members = $this->members;
        $members[MapKey::from($key)->value] = self::filterMember($member);

        return $this->newInstance($members);
    }

    /**
     * @param array<string, SfMember> $members
     */
    private function newInstance(array $members): self
    {
        return match (true) {
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

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function append(
        string $key,
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        $members = $this->members;
        unset($members[$key]);

        return $this->newInstance([...$members, MapKey::from($key)->value => self::filterMember($member)]);
    }

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function prepend(
        string $key,
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        $members = $this->members;
        unset($members[$key]);

        return $this->newInstance([MapKey::from($key)->value => self::filterMember($member), ...$members]);
    }

    /**
     * @param array{0:string, 1:SfMember|SfMemberInput} ...$pairs
     */
    public function push(array ...$pairs): static
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
     * @param array{0:string, 1:SfMember|SfMemberInput} ...$pairs
     */
    public function unshift(array ...$pairs): static
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
     * @param array{0:string, 1:SfMember|SfMemberInput} ...$members
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
     * @param array{0:string, 1:SfMember|SfMemberInput} $pair
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
     * @param iterable<string, SfMember|SfMemberInput> ...$others
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
     * @param MemberOrderedMap<string, SfMember>|iterable<array{0:string, 1:SfMember|SfMemberInput}> ...$others
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

    /**
     * @param Closure(SfMember, string): TMap $callback
     *
     * @template TMap
     *
     * @return Iterator<TMap>
     */
    public function map(Closure $callback): Iterator
    {
        foreach ($this->members as $offset => $member) {
            yield ($callback)($member, $offset);
        }
    }

    /**
     * @param Closure(TInitial|null, SfMember, string=): TInitial $callback
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(Closure $callback, mixed $initial = null): mixed
    {
        foreach ($this->members as $offset => $member) {
            $initial = $callback($initial, $member, $offset);
        }

        return $initial;
    }

    /**
     * @param Closure(SfMember, string): bool $callback
     */
    public function filter(Closure $callback): static
    {
        return new self(array_filter($this->members, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param Closure(array{0:string, 1:SfMember}, array{0:string, 1:SfMember}): int $callback
     */
    public function sort(Closure $callback): static
    {
        $members = iterator_to_array($this->toPairs());
        usort($members, $callback);

        return self::fromPairs($members);
    }
}
