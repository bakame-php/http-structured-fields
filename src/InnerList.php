<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Closure;
use DateTimeImmutable;
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
use function is_int;

use const ARRAY_FILTER_USE_BOTH;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1.1
 *
 * @phpstan-import-type SfItem from StructuredField
 * @phpstan-import-type SfItemInput from StructuredField
 * @phpstan-import-type SfInnerListPair from StructuredField
 *
 * @implements MemberList<int, SfItem>
 */
final class InnerList implements MemberList, ParameterAccess
{
    /** @var list<SfItem> */
    private readonly array $members;

    /**
     * @param iterable<SfItemInput> $members
     */
    private function __construct(iterable $members, private readonly Parameters $parameters)
    {
        $this->members = array_map($this->filterMember(...), array_values([...$members]));
    }

    /**
     * @param SfItemInput $member
     *
     * @return SfItem
     */
    private function filterMember(mixed $member): object
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
        }

        return match (true) {
            $member instanceof ValueAccess && $member instanceof ParameterAccess => $member,
            $member instanceof StructuredField => throw new InvalidArgument('An instance of "'.$member::class.'" can not be a member of "'.self::class.'".'),
            default => Item::new($member),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): static
    {
        [$members, $parameters] = Parser::new($rfc)->parseInnerList($httpValue);

        return new self(
            array_map(fn (array $member): Item => Item::fromAssociative(...$member), $members),
            Parameters::fromAssociative($parameters)
        );
    }

    /**
     * Returns a new instance with an iter.
     *
     * @param iterable<SfItemInput> $value
     * @param MemberOrderedMap<string, SfItem>|iterable<string, SfItemInput> $parameters
     */
    public static function fromAssociative(iterable $value, iterable $parameters): static
    {
        if (!$parameters instanceof Parameters) {
            $parameters = Parameters::fromAssociative($parameters);
        }

        return new self($value, $parameters);
    }

    /**
     * @param array{
     *     0:iterable<SfItemInput>,
     *     1:MemberOrderedMap<string, SfItem>|iterable<array{0:string, 1:SfItemInput}>
     * } $pair
     */
    public static function fromPair(array $pair): static
    {
        return match (true) {
            [] === $pair => self::new(), // @phpstan-ignore-line
            !array_is_list($pair) => throw new SyntaxError('The pair must be represented by an array as a list.'), // @phpstan-ignore-line
            2 !== count($pair) => throw new SyntaxError('The pair first member must be the member list and the second member the inner list parameters.'), // @phpstan-ignore-line
            default => new self($pair[0], !$pair[1] instanceof Parameters ? Parameters::fromPairs($pair[1]) : $pair[1]),
        };
    }

    /**
     * Returns a new instance.
     */
    public static function new(
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): static {
        return new self($members, Parameters::new());
    }

    public static function fromRfc9651(Stringable|string $httpValue): static
    {
        return self::fromHttpValue($httpValue, Ietf::Rfc9651);
    }

    public static function fromRfc8941(Stringable|string $httpValue): static
    {
        return self::fromHttpValue($httpValue, Ietf::Rfc8941);
    }

    public function toHttpValue(?Ietf $rfc = null): string
    {
        $rfc ??= Ietf::Rfc9651;

        return '('.implode(' ', array_map(fn (StructuredField $value): string => $value->toHttpValue($rfc), $this->members)).')'.$this->parameters->toHttpValue($rfc);
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

    /**
     * @return array{0:list<SfItem>, 1:MemberOrderedMap<string, SfItem>}
     */
    public function toPair(): array
    {
        return [$this->members, $this->parameters];
    }

    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function parameter(string $key): Token|ByteSequence|DisplayString|DateTimeImmutable|int|float|string|bool|null
    {
        try {
            return $this->parameters->get($key)->value();
        } catch (StructuredFieldError) {
            return null;
        }
    }

    /**
     * @return array{0:string, 1:Token|ByteSequence|DisplayString|DateTimeImmutable|int|float|string|bool}|array{}
     */
    public function parameterByIndex(int $index): array
    {
        try {
            $tuple = $this->parameters->pair($index);

            return [$tuple[0], $tuple[1]->value()];
        } catch (StructuredFieldError) {
            return [];
        }
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
     * @return SfItem
     */
    public function get(string|int $key): StructuredField
    {
        return $this->members[$this->filterIndex($key) ?? throw InvalidOffset::dueToIndexNotFound($key)];
    }

    /**
     * @return ?SfItem
     */
    public function first(): ?StructuredField
    {
        return $this->members[0] ?? null;
    }

    /**
     * @return ?SfItem
     */
    public function last(): ?StructuredField
    {
        return $this->members[$this->filterIndex(-1)] ?? null;
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
            default => new self([...array_values($membersToAdd), ...$this->members], $this->parameters),
        };
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
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
            default => new self([...$this->members, ...array_values($membersToAdd)], $this->parameters),
        };
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(
        int $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members
    ): static {
        $offset = $this->filterIndex($key) ?? throw InvalidOffset::dueToIndexNotFound($key);

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
        int $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        $offset = $this->filterIndex($key) ?? throw InvalidOffset::dueToIndexNotFound($key);
        $member = self::filterMember($member);

        return match (true) {
            $member->toHttpValue() === $this->members[$offset]->toHttpValue() => $this,
            default => new self(array_replace($this->members, [$offset => $member]), $this->parameters),
        };
    }

    public function remove(string|int ...$keys): static
    {
        $max = count($this->members);
        $indices = array_filter(
            array_map(
                fn (int $index): int|null => $this->filterIndex($index, $max),
                array_filter($keys, static fn (string|int $key): bool => is_int($key))
            ),
            fn (int|null $index): bool => null !== $index
        );

        return match (true) {
            [] === $indices => $this,
            count($indices) === $max => self::new(),
            default => new self(array_filter(
                $this->members,
                fn (int $key): bool => !in_array($key, $indices, true),
                ARRAY_FILTER_USE_KEY
            ), $this->parameters),
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
     * @return SfItem
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
     * @param Closure(SfItem, int): TMap $callback
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
     * @param Closure(TInitial|null, SfItem, int=): TInitial $callback
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
     * @param Closure(SfItem, int): bool $callback
     */
    public function filter(Closure $callback): static
    {
        return new self(array_filter($this->members, $callback, ARRAY_FILTER_USE_BOTH), $this->parameters);
    }

    /**
     * @param Closure(SfItem, SfItem): int $callback
     */
    public function sort(Closure $callback): static
    {
        $members = $this->members;
        usort($members, $callback);

        return new self($members, $this->parameters);
    }

    public function withParameters(Parameters $parameters): static
    {
        return ($this->parameters->toHttpValue() === $parameters->toHttpValue()) ? $this : new self($this->members, $parameters);
    }

    public function addParameter(string $key, StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->add($key, $member));
    }

    public function prependParameter(
        string $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(
        string $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function pushParameters(array ...$pairs): static
    {
        return $this->withParameters($this->parameters()->push(...$pairs));
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function unshiftParameters(array ...$pairs): static
    {
        return $this->withParameters($this->parameters()->unshift(...$pairs));
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function insertParameters(int $index, array ...$pairs): static
    {
        return $this->withParameters($this->parameters()->insert($index, ...$pairs));
    }

    /**
     * @param array{0:string, 1:SfItemInput} $pair
     */
    public function replaceParameter(int $index, array $pair): static
    {
        return $this->withParameters($this->parameters()->replace($index, $pair));
    }

    public function withoutParameterByKeys(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->removeByKeys(...$keys));
    }

    public function withoutParameterByIndices(int ...$indices): static
    {
        return $this->withParameters($this->parameters()->removeByIndices(...$indices));
    }

    public function withoutAnyParameter(): static
    {
        return $this->withParameters(Parameters::new());
    }

    /**
     * @deprecated since version 1.1
     * @see ParameterAccess::withoutParameterByKeys()
     * @codeCoverageIgnore
     */
    public function withoutParameter(string ...$keys): static
    {
        return $this->withoutParameterByKeys(...$keys);
    }
}
