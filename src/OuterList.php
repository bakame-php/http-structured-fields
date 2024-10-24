<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Closure;
use DateTimeInterface;
use Iterator;
use Stringable;

use function array_filter;
use function array_is_list;
use function array_map;
use function array_replace;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_int;
use function is_iterable;

use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#name-lists
 *
 * @phpstan-import-type SfItem from StructuredField
 * @phpstan-import-type SfMember from StructuredField
 * @phpstan-import-type SfMemberInput from StructuredField
 * @phpstan-import-type SfInnerListPair from StructuredField
 * @phpstan-import-type SfItemPair from StructuredField
 *
 * @implements MemberList<int, SfMember>
 */
final class OuterList implements MemberList
{
    /** @var list<SfMember> */
    private readonly array $members;

    /**
     * @param SfMember|SfMemberInput ...$members
     */
    private function __construct(
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ) {
        $this->members = array_map($this->filterMember(...), array_values([...$members]));
    }

    /**
     * @param SfMember|SfMemberInput $member
     *
     * @return SfMember
     */
    private function filterMember(mixed $member): object
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
        }

        return match (true) {
            $member instanceof ParameterAccess && ($member instanceof MemberList || $member instanceof ValueAccess) => $member,
            $member instanceof StructuredField => throw new InvalidArgument('An instance of "'.$member::class.'" can not be a member of "'.self::class.'".'),
            default => Item::new($member), /* @phpstan-ignore-line */
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1
     */
    public static function fromHttpValue(Stringable|string $httpValue, ListParser $parser = new Parser()): self
    {
        $converter = fn (array $member): InnerList|Item => match (true) {
            is_array($member[0]) => InnerList::fromAssociative(
                array_map(fn (array $item) => Item::fromAssociative(...$item), $member[0]),
                $member[1]
            ),
            default => Item::fromAssociative(...$member),
        };

        return new self(...array_map($converter, $parser->parseList($httpValue)));
    }

    /**
     * @param iterable<SfInnerListPair|SfItemPair>|MemberList<int, SfItem> $pairs
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
            $pairs instanceof MemberList => new self($pairs),
            default => new self(...(function (iterable $pairs) use ($converter) {
                foreach ($pairs as $member) {
                    yield $converter($member);
                }
            })($pairs)),
        };
    }

    /**
     * @param SfMember|SfMemberInput ...$members
     */
    public static function new(iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members): self
    {
        return new self(...$members);
    }

    public static function fromRfc9651(Stringable|string $httpValue): self
    {
        return self::fromHttpValue($httpValue, new Parser(Ietf::Rfc9651));
    }

    public static function fromRfc8941(Stringable|string $httpValue): self
    {
        return self::fromHttpValue($httpValue, new Parser(Ietf::Rfc8941));
    }

    public function toHttpValue(?Ietf $rfc = null): string
    {
        $rfc ??= Ietf::Rfc9651;

        return implode(', ', array_map(fn (StructuredField $member): string => $member->toHttpValue($rfc), $this->members));
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

    public function getIterator(): Iterator
    {
        yield from $this->members;
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
     * @return array<int>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function has(string|int ...$keys): bool
    {
        $max = count($this->members);
        foreach ($keys as $offset) {
            if (null === $this->filterIndex($offset, $max)) {
                return false;
            }
        }

        return [] !== $keys;
    }

    private function filterIndex(string|int $index, int|null $max = null): int|null
    {
        if (!is_int($index)) {
            return null;
        }

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
     * @return SfMember
     */
    public function get(string|int $key): StructuredField
    {
        return $this->members[$this->filterIndex($key) ?? throw InvalidOffset::dueToIndexNotFound($key)];
    }

    /**
     * @return ?SfMember
     */
    public function first(): ?StructuredField
    {
        return $this->members[0] ?? null;
    }

    /**
     * @return ?SfMember
     */
    public function last(): ?StructuredField
    {
        return $this->members[$this->filterIndex(-1)] ?? null;
    }

    /**
     * Inserts members at the beginning of the list.
     *
     * @param SfMember|SfMemberInput ...$members
     */
    public function unshift(
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): static {
        $membersToAdd = array_reduce(
            $members,
            function (array $carry, $member) {
                if ($member instanceof StructuredFieldProvider) {
                    $member = $member->toStructuredField();
                }

                return [...$carry, ...$member instanceof InnerList ? [...$member] : [$member]];
            },
            []
        );

        return match (true) {
            [] === $membersToAdd => $this,
            default => new self(...array_values($membersToAdd), ...$this->members),
        };
    }

    /**
     * Inserts members at the end of the list.
     *
     * @param SfMember|SfMemberInput ...$members
     */
    public function push(
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): static {
        $membersToAdd = array_reduce(
            $members,
            function (array $carry, $member) {
                if ($member instanceof StructuredFieldProvider) {
                    $member = $member->toStructuredField();
                }

                return [...$carry, ...$member instanceof InnerList ? [...$member] : [$member]];
            },
            []
        );

        return match (true) {
            [] === $membersToAdd => $this,
            default => new self(...$this->members, ...array_values($membersToAdd)),
        };
    }

    /**
     * Inserts members starting at the given index.
     *
     * @param SfMember|SfMemberInput ...$members
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $key,
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): static {
        $offset = $this->filterIndex($key) ?? throw InvalidOffset::dueToIndexNotFound($key);

        return match (true) {
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            [] === $members => $this,
            default => (function (array $newMembers) use ($offset, $members) {
                array_splice($newMembers, $offset, 0, $members);

                return new self(...$newMembers);
            })($this->members),
        };
    }

    /**
     * @param SfMember|SfMemberInput $member
     */
    public function replace(
        int $key,
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        $offset = $this->filterIndex($key) ?? throw InvalidOffset::dueToIndexNotFound($key);
        $member = self::filterMember($member);

        return match (true) {
            $member->toHttpValue() === $this->members[$offset]->toHttpValue() => $this,
            default => new self(...array_replace($this->members, [$offset => $member])),
        };
    }

    public function remove(string|int ...$keys): static
    {
        $max = count($this->members);
        $offsets = array_filter(
            array_map(
                fn (int $index): int|null => $this->filterIndex($index, $max),
                array_filter($keys, static fn (string|int $key): bool => is_int($key))
            ),
            fn (int|null $index): bool => null !== $index
        );

        return match (true) {
            [] === $offsets => $this,
            $max === count($offsets) => new self(),
            default => new self(...array_filter(
                $this->members,
                fn (int $key): bool => !in_array($key, $offsets, true),
                ARRAY_FILTER_USE_KEY
            )),
        };
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param int $offset
     *
     * @return SfMember
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
     * @param Closure(SfMember, int): TMap $callback
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
     * @param Closure(TInitial|null, SfMember, int=): TInitial $callback
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
     * @param Closure(SfMember, int): bool $callback
     */
    public function filter(Closure $callback): self
    {
        return new self(...array_filter($this->members, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param Closure(SfMember, SfMember): int $callback
     */
    public function sort(Closure $callback): self
    {
        $members = $this->members;
        usort($members, $callback);

        return new self(...$members);
    }
}
