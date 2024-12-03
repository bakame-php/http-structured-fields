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
use function uasort;

use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#name-lists
 *
 * @phpstan-import-type SfMemberInput from StructuredFieldProvider
 * @phpstan-import-type SfInnerListPair from StructuredFieldProvider
 * @phpstan-import-type SfItemPair from StructuredFieldProvider
 *
 * @implements ArrayAccess<int, InnerList|Item>
 * @implements IteratorAggregate<int, InnerList|Item>
 */
final class OuterList implements ArrayAccess, Countable, IteratorAggregate
{
    /** @var list<InnerList|Item> */
    private readonly array $members;

    /**
     * @param SfMemberInput ...$members
     */
    private function __construct(
        iterable|StructuredFieldProvider|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ) {
        $this->members = array_map($this->filterMember(...), array_values([...$members]));
    }

    /**
     * @param SfMemberInput $member
     */
    private function filterMember(mixed $member): InnerList|Item
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
            if ($member instanceof Item || $member instanceof InnerList) {
                return $member;
            }

            throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Item::class.' or an '.InnerList::class.'; '.$member::class.' given.');
        }

        return match (true) {
            $member instanceof InnerList,
            $member instanceof Item => $member,
            is_iterable($member) => InnerList::new(...$member),
            default => Item::new($member),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1
     */
    public static function fromHttpValue(Stringable|string $httpValue, Ietf $rfc = Ietf::Rfc9651): self
    {
        return self::fromPairs((new Parser($rfc))->parseList($httpValue)); /* @phpstan-ignore-line */
    }

    /**
     * @param StructuredFieldProvider|iterable<SfInnerListPair|SfItemPair> $pairs
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
                if ($pair instanceof Item || $pair instanceof InnerList) {
                    return $pair;
                }

                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Item::class.' or an '.InnerList::class.'; '.$pair::class.' given.');
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

            if (!in_array(count($pair), [1, 2], true)) {
                throw new SyntaxError('The pair first member is the item value; its second member is the item parameters.');
            }

            return is_iterable($pair[0]) ? InnerList::fromPair($pair) : Item::fromPair($pair);
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
     * @param SfMemberInput ...$members
     */
    public static function new(iterable|StructuredFieldProvider|InnerList|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members): self
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

    public function toHttpValue(Ietf $rfc = Ietf::Rfc9651): string
    {
        return implode(', ', array_map(fn (Item|InnerList $member): string => $member->toHttpValue($rfc), $this->members));
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

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->toHttpValue() === $this->toHttpValue();
    }

    /**
     * Apply the callback if the given "condition" is (or resolves to) true.
     *
     * @param (callable($this): bool)|bool $condition
     * @param callable($this): (self|null) $onSuccess
     * @param ?callable($this): (self|null) $onFail
     */
    public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): self
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
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

    private function filterIndex(int $index, ?int $max = null): ?int
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
     * @param SfMemberInput ...$members
     */
    public function unshift(
        StructuredFieldProvider|InnerList|Item|iterable|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
     * @param SfMemberInput ...$members
     */
    public function push(
        iterable|StructuredFieldProvider|InnerList|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
     * @param SfMemberInput ...$members
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $index,
        iterable|StructuredFieldProvider|InnerList|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
     * @param SfMemberInput $member
     */
    public function replace(
        int $index,
        iterable|StructuredFieldProvider|InnerList|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): self {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);
        $member = self::filterMember($member);

        return match (true) {
            $member->equals($this->members[$offset]) => $this,
            default => new self(...array_replace($this->members, [$offset => $member])),
        };
    }

    public function removeByIndices(int ...$indices): self
    {
        $max = count($this->members);
        $offsets = array_filter(
            array_map(fn (int $index): ?int => $this->filterIndex($index, $max), $indices),
            fn (?int $index): bool => null !== $index
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
        uasort($members, $callback);

        return new self(...$members);
    }
}
