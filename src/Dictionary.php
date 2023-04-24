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
use function is_array;
use function is_iterable;
use function is_string;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.2
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
        if ($pairs instanceof MemberOrderedMap) {
            return new self($pairs);
        }

        return new self((function (iterable $pairs) {
            foreach ($pairs as [$key, $member]) {
                yield $key => $member;
            }
        })($pairs));
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.2
     *
     * @throws SyntaxError If the string is not a valid
     */
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        return new self((function (iterable $members) {
            foreach ($members as $key => $member) {
                if (!is_array($member[0])) {
                    yield $key => Item::fromAssociative(...$member);

                    continue;
                }

                $member[0] = array_map(fn (array $item) => Item::fromAssociative(...$item), $member[0]);
                yield $key => InnerList::fromAssociative(...$member);
            }
        })(Parser::parseDictionary($httpValue)));
    }

    public function toHttpValue(): string
    {
        $members = [];
        foreach ($this->members as $key => $member) {
            $members[] = match (true) {
                $member instanceof ValueAccess && true === $member->value() => $key.$member->parameters()->toHttpValue(),
                default => $key.'='.$member->toHttpValue(),
            };
        }

        return implode(', ', $members);
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
     * @return Iterator<array{0:string, 1:SfMember}>
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
     * @throws SyntaxError   If the key is invalid
     * @throws InvalidOffset If the key is not found
     *
     * @return SfMember
     */
    public function get(string|int $key): StructuredField
    {
        if (!$this->has($key)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->members[$key];
    }

    public function hasPair(int ...$indexes): bool
    {
        foreach ($indexes as $index) {
            if (null === $this->filterIndex($index)) {
                return false;
            }
        }

        return [] !== $indexes;
    }

    /**
     * Filters and format instance index.
     */
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
     * @throws InvalidOffset If the key is not found
     *
     * @return array{0:string, 1:SfMember}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return [...$this->toPairs()][$offset];
    }

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function add(string $key, iterable|StructuredField|Token|ByteSequence|DateTimeInterface|string|int|float|bool $member): static
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
        if ($members == $this->members) {
            return $this;
        }

        return new self($members);
    }

    public function remove(string|int ...$keys): static
    {
        /** @var array<array-key, true> $indexes */
        $indexes = array_fill_keys($keys, true);
        $pairs = [];
        foreach ($this->toPairs() as $index => $pair) {
            if (!isset($indexes[$index]) && !isset($indexes[$pair[0]])) {
                $pairs[] = $pair;
            }
        }

        if (count($this->members) === count($pairs)) {
            return $this;
        }

        return self::fromPairs($pairs);
    }

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function append(string $key, iterable|StructuredField|Token|ByteSequence|DateTimeInterface|string|int|float|bool $member): static
    {
        $members = $this->members;
        unset($members[$key]);
        $members[MapKey::from($key)->value] = self::filterMember($member);

        return $this->newInstance($members);
    }

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function prepend(string $key, iterable|StructuredField|Token|ByteSequence|DateTimeInterface|string|int|float|bool $member): static
    {
        $members = $this->members;
        unset($members[$key]);
        $members = [MapKey::from($key)->value => self::filterMember($member), ...$members];

        return $this->newInstance($members);
    }

    /**
     * @param array{0:string, 1:SfMember|SfMemberInput} ...$pairs
     */
    public function push(array ...$pairs): self
    {
        if ([] === $pairs) {
            return $this;
        }

        $newPairs = iterator_to_array($this->toPairs());
        foreach ($pairs as $pair) {
            $newPairs[] = $pair;
        }

        return self::fromPairs($newPairs);
    }

    /**
     * @param array{0:string, 1:SfMember|SfMemberInput} ...$pairs
     */
    public function unshift(array ...$pairs): self
    {
        if ([] === $pairs) {
            return $this;
        }

        foreach ($this->members as $key => $member) {
            $pairs[] = [$key, $member];
        }

        return self::fromPairs($pairs);
    }

    /**
     * @param array{0:string, 1:SfMember|SfMemberInput} ...$members
     */
    public function insert(int $index, array ...$members): static
    {
        $offset = $this->filterIndex($index);

        return match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
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
     * @param array{0:string, 1:SfMember|SfMemberInput} $member
     */
    public function replace(int $index, array $member): static
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $member[1] = self::filterMember($member[1]);
        $pairs = iterator_to_array($this->toPairs());
        if ($pairs[$offset] == $member) {
            return $this;
        }

        return self::fromPairs(array_replace($pairs, [$offset => $member]));
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
}
