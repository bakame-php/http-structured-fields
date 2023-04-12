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
use function is_string;
use function trim;
use const ARRAY_FILTER_USE_KEY;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
 *
 * @phpstan-import-type SfTypeInput from StructuredField
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
            $member instanceof ValueAccess && $member instanceof ParameterAccess => $member->parameters()->hasNoMembers() ? $member : throw new InvalidArgument('The instance of "'.$member::class.'" is not a Bare Item.'),
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
        if ($pairs instanceof MemberOrderedMap) {
            $pairs = $pairs->toPairs();
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
     * @return Iterator<array{0:string, 1:SfItem}>
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
     * @return SfItem
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
            try {
                $this->filterIndex($index);
            } catch (InvalidOffset) {
                return false;
            }
        }

        return [] !== $indexes;
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
     * @throws InvalidOffset if the index is not found
     *
     * @return array{0:string, 1:SfItem}
     */
    public function pair(int $index): array
    {
        return [...$this->toPairs()][$this->filterIndex($index)];
    }

    public function add(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|string|int|float|bool $member): static
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
        if ($members == $this->members) {
            return $this;
        }

        return new self($members);
    }

    public function remove(string|int ...$keys): static
    {
        $members = array_filter(
            $this->members,
            fn (string|int $key): bool => !in_array($key, $keys, true),
            ARRAY_FILTER_USE_KEY
        );

        return $this->newInstance($members);
    }

    public function append(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|string|int|float|bool $member): static
    {
        $members = $this->members;
        unset($members[$key]);
        $members[MapKey::from($key)->value] = self::filterMember($member);

        return $this->newInstance($members);
    }

    public function prepend(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|string|int|float|bool $member): static
    {
        $members = $this->members;
        unset($members[$key]);
        $members = [MapKey::from($key)->value => self::filterMember($member), ...$members];

        return $this->newInstance($members);
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
