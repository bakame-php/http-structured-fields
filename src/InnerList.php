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
use function uasort;

use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1.1
 *
 * @phpstan-import-type SfType from StructuredFieldProvider
 * @phpstan-import-type SfItemInput from StructuredFieldProvider
 * @phpstan-import-type SfInnerListPair from StructuredFieldProvider
 * @phpstan-import-type SfParameterInput from StructuredFieldProvider
 *
 * @implements  ArrayAccess<int, Item>
 * @implements  IteratorAggregate<int, Item>
 */
final class InnerList implements ArrayAccess, Countable, IteratorAggregate
{
    use ParameterAccess;

    /** @var list<Item> */
    private readonly array $members;
    private readonly Parameters $parameters;

    /**
     * @param iterable<SfItemInput> $members
     */
    private function __construct(iterable $members, ?Parameters $parameters = null)
    {
        $this->members = array_map($this->filterMember(...), array_values([...$members]));
        $this->parameters = $parameters ?? Parameters::new();
    }

    /**
     * @param SfItemInput $member
     */
    private function filterMember(mixed $member): Item
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
            if (!$member instanceof Item) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Item::class.'; '.$member::class.' given.');
            }

            return $member;
        }

        if (!$member instanceof Item) {
            return Item::new($member);
        }

        return $member;
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1
     */
    public static function fromHttpValue(Stringable|string $httpValue, Ietf $rfc = Ietf::Rfc9651): self
    {
        return self::fromPair((new Parser($rfc))->parseInnerList($httpValue));
    }

    /**
     * Returns a new instance with an iter.
     *
     * @param iterable<SfItemInput> $value
     * @param Parameters|iterable<string, SfItemInput> $parameters
     */
    public static function fromAssociative(
        iterable $value,
        StructuredFieldProvider|Parameters|iterable $parameters
    ): self {
        if ($parameters instanceof StructuredFieldProvider) {
            $parameters = $parameters->toStructuredField();
            if (!$parameters instanceof Parameters) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$parameters::class.' given.');
            }
        }

        if (!$parameters instanceof Parameters) {
            return new self($value, Parameters::fromAssociative($parameters));
        }

        return new self($value, $parameters);
    }

    /**
     * @param array{0:iterable<SfItemInput>, 1?:Parameters|SfParameterInput}|array<mixed> $pair
     */
    public static function fromPair(array $pair): self
    {
        if ([] === $pair) {
            return self::new();
        }

        if (!array_is_list($pair) || 2 < count($pair)) {
            throw new SyntaxError('The pair must be represented by an non-empty array as a list containing at most 2 members.');
        }

        if (1 === count($pair)) {
            return new self($pair[0]);
        }

        if ($pair[1] instanceof StructuredFieldProvider) {
            $pair[1] = $pair[1]->toStructuredField();
            if (!$pair[1] instanceof Parameters) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$pair[1]::class.' given.');
            }
        }

        if (!$pair[1] instanceof Parameters) {
            return new self($pair[0], Parameters::fromPairs($pair[1]));
        }

        return new self($pair[0], $pair[1]);
    }

    /**
     * Returns a new instance.
     */
    public static function new(
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): self {
        return new self($members);
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
        return '('.implode(' ', array_map(fn (Item $value): string => $value->toHttpValue($rfc), $this->members)).')'.$this->parameters->toHttpValue($rfc);
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

    /**
     * @return array{0:list<Item>, 1:Parameters}
     */
    public function toPair(): array
    {
        return [$this->members, $this->parameters];
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
        foreach ($indices as $offset) {
            if (null === $this->filterIndex($offset, $max)) {
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
     * @param ?callable(Item): (bool|string) $validate
     *
     * @throws SyntaxError|Violation|StructuredFieldError
     */
    public function getByIndex(int $index, ?callable $validate = null): Item
    {
        $value = $this->members[$this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index)];
        if (null === $validate) {
            return $value;
        }

        if (true === ($exceptionMessage = $validate($value))) {
            return $value;
        }

        if (!is_string($exceptionMessage) || '' === trim($exceptionMessage)) {
            $exceptionMessage = "The item at '{index}' whose value is '{value}' failed validation.";
        }

        throw new Violation(strtr($exceptionMessage, ['{index}' => $index, '{value}' => $value->toHttpValue()]));
    }

    public function first(): ?Item
    {
        return $this->members[0] ?? null;
    }

    public function last(): ?Item
    {
        return $this->members[$this->filterIndex(-1)] ?? null;
    }

    public function withParameters(StructuredFieldProvider|Parameters $parameters): static
    {
        if ($parameters instanceof StructuredFieldProvider) {
            $parameters = $parameters->toStructuredField();
            if (!$parameters instanceof Parameters) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$parameters::class.' given.');
            }
        }

        return $this->parameters->equals($parameters) ? $this : new self($this->members, $parameters);
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
            default => new self([...array_values($membersToAdd), ...$this->members], $this->parameters),
        };
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
            default => new self([...$this->members, ...array_values($membersToAdd)], $this->parameters),
        };
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $index,
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): self {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);

        return match (true) {
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            [] === $members => $this,
            default => (function (array $newMembers) use ($offset, $members) {
                array_splice($newMembers, $offset, 0, $members);

                return new self($newMembers, $this->parameters);
            })($this->members),
        };
    }

    public function replace(
        int $index,
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): self {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);
        $member = self::filterMember($member);

        return match (true) {
            $member->equals($this->members[$offset]) => $this,
            default => new self(array_replace($this->members, [$offset => $member]), $this->parameters),
        };
    }

    public function removeByIndices(int ...$indices): self
    {
        $max = count($this->members);
        $indices = array_filter(
            array_map(fn (int $index): ?int => $this->filterIndex($index, $max), $indices),
            fn (?int $index): bool => null !== $index
        );

        return match (true) {
            [] === $indices => $this,
            count($indices) === $max => self::new(),
            default => new self(array_filter(
                $this->members,
                fn (int $offset): bool => !in_array($offset, $indices, true),
                ARRAY_FILTER_USE_KEY
            ), $this->parameters),
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
    public function offsetGet(mixed $offset): Item
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
     * @param callable(Item, int): TMap $callback
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
     * @param callable(TInitial|null, Item, int=): TInitial $callback
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
     * @param callable(Item, int): bool $callback
     */
    public function filter(callable $callback): self
    {
        $members = array_filter($this->members, $callback, ARRAY_FILTER_USE_BOTH);
        if ($members === $this->members) {
            return $this;
        }

        return new self($members, $this->parameters);
    }

    /**
     * @param callable(Item, Item): int $callback
     */
    public function sort(callable $callback): self
    {
        $members = $this->members;
        uasort($members, $callback);

        return new self($members, $this->parameters);
    }
}
