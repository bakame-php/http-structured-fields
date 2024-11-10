<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Bakame\Http\StructuredFields\Validation\Violation;
use Countable;
use DateTimeInterface;
use Iterator;
use IteratorAggregate;
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
use function is_iterable;

use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#name-lists
 *
 * @phpstan-import-type SfMemberInput from StructuredField
 * @phpstan-import-type SfInnerListPair from StructuredField
 * @phpstan-import-type SfItemPair from StructuredField
 *
 * @implements ArrayAccess<int, InnerList|Item>
 * @implements IteratorAggregate<int, InnerList|Item>
 */
final class OuterList implements ArrayAccess, Countable, IteratorAggregate, StructuredField
{
    /** @var list<InnerList|Item> */
    private readonly array $members;

    /**
     * @param InnerList|Item|SfMemberInput ...$members
     */
    private function __construct(
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ) {
        $this->members = array_map($this->filterMember(...), array_values([...$members]));
    }

    /**
     * @param InnerList|Item|SfMemberInput $member
     */
    private function filterMember(mixed $member): InnerList|Item
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
        }

        return match (true) {
            $member instanceof InnerList || $member instanceof Item => $member,
            $member instanceof StructuredField => throw new InvalidArgument('An instance of "'.$member::class.'" can not be a member of "'.self::class.'".'),
            is_iterable($member) => InnerList::new(...$member),
            default => Item::new($member),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1
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

        return new self(...array_map($converter, Parser::new($rfc)->parseList($httpValue)));
    }

    /**
     * @param iterable<SfInnerListPair|SfItemPair>|InnerList $pairs
     */
    public static function fromPairs(StructuredFieldProvider|iterable $pairs): self
    {
        if ($pairs instanceof StructuredFieldProvider) {
            $pairs = $pairs->toStructuredField();
        }

        if (!is_iterable($pairs)) {
            throw new InvalidArgument('The "'.$pairs::class.'" instance can not be used for creating a .'.self::class.' structured field.');
        }

        $converter = function (mixed $pair): InnerList|Item {
            if ($pair instanceof StructuredFieldProvider) {
                $pair = $pair->toStructuredField();
            }

            if ($pair instanceof InnerList || $pair instanceof Item) {
                return $pair;
            }

            if (!is_array($pair)) {
                return Item::new($pair); /* @phpstan-ignore-line */
            }

            if (!array_is_list($pair)) {
                throw new SyntaxError('The pair must be represented by an array as a list.');
            }

            if ([] === $pair) {
                return InnerList::new();
            }

            [$member, $parameters] = match (count($pair)) {
                2 => $pair,
                1 => [$pair[0], []],
                default => throw new SyntaxError('The pair first member is the item value; its second member is the item parameters.'),
            };

            return is_iterable($member) ? InnerList::fromPair([$member, $parameters]) : Item::fromPair([$member, $parameters]);
        };

        return match (true) {
            $pairs instanceof OuterList,
            $pairs instanceof InnerList => new self($pairs),
            default => new self(...(function (iterable $pairs) use ($converter) {
                foreach ($pairs as $member) {
                    yield $converter($member);
                }
            })($pairs)),
        };
    }

    /**
     * @param InnerList|Item|SfMemberInput ...$members
     */
    public static function new(iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members): self
    {
        return new self(...$members);
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

    public function isEmpty(): bool
    {
        return !$this->isNotEmpty();
    }

    public function isNotEmpty(): bool
    {
        return [] !== $this->members;
    }

    /**
     * @return array<int>
     */
    public function indices(): array
    {
        return array_keys($this->members);
    }

    public function hasIndices(int ...$indices): bool
    {
        $max = count($this->members);
        foreach ($indices as $index) {
            if (null === $this->filterIndex($index, $max)) {
                return false;
            }
        }

        return [] !== $indices;
    }

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
     * @param ?callable(InnerList|Item): (bool|string) $validate
     *
     * @throws SyntaxError|Violation|StructuredFieldError
     */
    public function getByIndex(int $index, ?callable $validate = null): InnerList|Item
    {
        $value = $this->members[$this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index)];
        if (null === $validate) {
            return $value;
        }

        if (true === ($exceptionMessage = $validate($value))) {
            return $value;
        }

        if (!is_string($exceptionMessage) || '' === trim($exceptionMessage)) {
            $exceptionMessage = "The member at position '{index}' whose value is '{value}' failed validation.";
        }

        throw new Violation(strtr($exceptionMessage, ['{index}' => $index, '{value}' => $value->toHttpValue()]));
    }

    public function first(): InnerList|Item|null
    {
        return $this->members[0] ?? null;
    }

    public function last(): InnerList|Item|null
    {
        return $this->members[$this->filterIndex(-1)] ?? null;
    }

    /**
     * Inserts members at the beginning of the list.
     *
     * @param StructuredFieldProvider|InnerList|Item|SfMemberInput ...$members
     */
    public function unshift(
        StructuredFieldProvider|StructuredField|iterable|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): self {
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
     * @param InnerList|Item|SfMemberInput ...$members
     */
    public function push(
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): self {
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
     * @param InnerList|Item|SfMemberInput ...$members
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $index,
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): self {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);

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
     * @param InnerList|Item|SfMemberInput $member
     */
    public function replace(
        int $index,
        iterable|StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): self {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);
        $member = self::filterMember($member);

        return match (true) {
            $member->toHttpValue() === $this->members[$offset]->toHttpValue() => $this,
            default => new self(...array_replace($this->members, [$offset => $member])),
        };
    }

    public function removeByIndices(int ...$indices): self
    {
        $max = count($this->members);
        $offsets = array_filter(
            array_map(fn (int $index): int|null => $this->filterIndex($index, $max), $indices),
            fn (int|null $index): bool => null !== $index
        );

        return match (true) {
            [] === $offsets => $this,
            $max === count($offsets) => new self(),
            default => new self(...array_filter(
                $this->members,
                fn (int $index): bool => !in_array($index, $offsets, true),
                ARRAY_FILTER_USE_KEY
            )),
        };
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasIndices($offset);
    }

    /**
     * @param int $offset
     */
    public function offsetGet(mixed $offset): InnerList|Item
    {
        return $this->getByIndex($offset);
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
     * @param callable(InnerList|Item, int): TMap $callback
     *
     * @template TMap
     *
     * @return Iterator<TMap>
     */
    public function map(callable $callback): Iterator
    {
        foreach ($this->members as $offset => $member) {
            yield ($callback)($member, $offset);
        }
    }

    /**
     * @param callable(TInitial|null, InnerList|Item, int): TInitial $callback
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        foreach ($this->members as $offset => $member) {
            $initial = $callback($initial, $member, $offset);
        }

        return $initial;
    }

    /**
     * @param callable(InnerList|Item, int): bool $callback
     */
    public function filter(callable $callback): self
    {
        $members = array_filter($this->members, $callback, ARRAY_FILTER_USE_BOTH);
        if ($members === $this->members) {
            return $this;
        }

        return new self(...$members);
    }

    /**
     * @param callable(InnerList|Item, InnerList|Item): int $callback
     */
    public function sort(callable $callback): self
    {
        $members = $this->members;
        usort($members, $callback);

        return new self(...$members);
    }
}
