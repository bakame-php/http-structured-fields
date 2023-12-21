<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use DateTimeInterface;
use Iterator;
use Stringable;

use function array_filter;
use function array_map;
use function array_replace;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_int;

use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#name-lists
 *
 * @phpstan-import-type SfMember from StructuredField
 * @phpstan-import-type SfMemberInput from StructuredField
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
    private function __construct(iterable|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members)
    {
        $this->members = array_map($this->filterMember(...), array_values([...$members]));
    }

    /**
     * @param SfMember|SfMemberInput $member
     *
     * @return SfMember
     */
    private function filterMember(mixed $member): object
    {
        return match (true) {
            $member instanceof ParameterAccess && ($member instanceof MemberList || $member instanceof ValueAccess) => $member,
            $member instanceof StructuredField => throw new InvalidArgument('An instance of "'.$member::class.'" can not be a member of "'.self::class.'".'),
            is_iterable($member) => InnerList::new(...$member),
            default => Item::new($member),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1
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
     * @param SfMember|SfMemberInput ...$members
     */
    public static function new(iterable|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members): self
    {
        return new self(...$members);
    }

    public function toHttpValue(): string
    {
        return implode(', ', array_map(fn (StructuredField $member): string => $member->toHttpValue(), $this->members));
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
     * Inserts members at the beginning of the list.
     *
     * @param SfMember|SfMemberInput ...$members
     */
    public function unshift(iterable|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members): static
    {
        return match (true) {
            [] === $members => $this,
            default => new self(...array_values($members), ...$this->members),
        };
    }

    /**
     * Inserts members at the end of the list.
     *
     * @param SfMember|SfMemberInput ...$members
     */
    public function push(iterable|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members): static
    {
        return match (true) {
            [] === $members => $this,
            default => new self(...$this->members, ...array_values($members)),
        };
    }

    /**
     * Inserts members starting at the given index.
     *
     * @param SfMember|SfMemberInput ...$members
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $key, iterable|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool ...$members): static
    {
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
    public function replace(int $key, iterable|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member): static
    {
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
}
