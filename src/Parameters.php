<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Bakame\Http\StructuredFields\Validation\Violation;
use CallbackFilterIterator;
use Countable;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Iterator;
use IteratorAggregate;
use Stringable;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_int;
use function is_string;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1.2
 *
 * @phpstan-import-type SfItemInput from StructuredFieldProvider
 * @phpstan-import-type SfType from StructuredFieldProvider
 *
 * @implements ArrayAccess<string, InnerList|Item>
 * @implements IteratorAggregate<int, array{0:string, 1:Item}>
 */
final class Parameters implements ArrayAccess, Countable, IteratorAggregate
{
    /** @var array<string, Item> */
    private readonly array $members;

    /**
     * @param iterable<string, SfItemInput> $members
     */
    private function __construct(iterable $members = [])
    {
        $filteredMembers = [];
        foreach ($members as $name => $member) {
            $filteredMembers[MapKey::from($name)->value] = self::filterMember($member);
        }

        $this->members = $filteredMembers;
    }

    /**
     * @param SfItemInput $member
     */
    private static function filterMember(mixed $member): Item
    {
        if ($member instanceof StructuredFieldProvider) {
            $member = $member->toStructuredField();
        }

        return match (true) {
            !$member instanceof Item => Item::new($member),
            $member->parameters()->isEmpty() => $member,
            default => throw new InvalidArgument('The "'.$member::class.'" instance is not a Bare Item.'),
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
     * its keys represent the dictionary entry name
     * its values represent the dictionary entry value
     *
     * @param StructuredFieldProvider|iterable<string, SfItemInput> $members
     */
    public static function fromAssociative(StructuredFieldProvider|iterable $members): self
    {
        if ($members instanceof StructuredFieldProvider) {
            $structuredField = $members->toStructuredField();

            return match (true) {
                $structuredField instanceof Dictionary,
                $structuredField instanceof Parameters => new self($structuredField->toAssociative()),
                default => throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a structured field container; '.$structuredField::class.' given.'),
            };
        }

        return new self($members);
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each member is composed of an array with two elements
     * the first member represents the instance entry name
     * the second member represents the instance entry value
     *
     * @param StructuredFieldProvider|iterable<array{0:string, 1:SfItemInput}> $pairs
     */
    public static function fromPairs(StructuredFieldProvider|iterable $pairs): self
    {
        if ($pairs instanceof StructuredFieldProvider) {
            $pairs = $pairs->toStructuredField();
        }

        if (!is_iterable($pairs)) {
            throw new InvalidArgument('The "'.$pairs::class.'" instance can not be used for creating a .'.self::class.' structured field.');
        }

        return match (true) {
            $pairs instanceof Parameters,
            $pairs instanceof Dictionary => new self($pairs->toAssociative()),
            default => new self((function (iterable $pairs) {
                foreach ($pairs as [$name, $member]) {
                    yield $name => $member;
                }
            })($pairs)),
        };
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.1.2
     *
     * @throws SyntaxError|Exception If the string is not a valid
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): self
    {
        return self::fromPairs(Parser::new($rfc)->parseParameters($httpValue)); /* @phpstan-ignore-line */
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
        $formatter = static fn (Item $member, string $offset): string => match (true) {
            true === $member->value() => ';'.$offset,
            default => ';'.$offset.'='.$member->toHttpValue($rfc),
        };

        return implode('', array_map($formatter, $this->members, array_keys($this->members)));
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
     * @return Iterator<string, Item>
     */
    public function toAssociative(): Iterator
    {
        yield from $this->members;
    }

    /**
     * @return Iterator<int, array{0:string, 1:Item}>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, $member];
        }
    }

    /**
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->members);
    }

    /**
     * Tells whether the instance contain a members at the specified offsets.
     */
    public function hasNames(string ...$names): bool
    {
        foreach ($names as $name) {
            if (!array_key_exists($name, $this->members)) {
                return false;
            }
        }

        return [] !== $names;
    }

    /**
     * @param ?callable(SfType): (bool|string) $validate
     *
     * @throws Violation|InvalidOffset
     */
    public function getByName(string $name, ?callable $validate = null): Item
    {
        $value = $this->members[$name] ?? throw InvalidOffset::dueToNameNotFound($name);
        if (null === $validate) {
            return $value;
        }

        if (true === ($exceptionMessage = $validate($value->value()))) {
            return $value;
        }

        if (!is_string($exceptionMessage) || '' === trim($exceptionMessage)) {
            $exceptionMessage = "The parameter '{name}' whose value is '{value}' failed validation.";
        }

        throw new Violation(strtr($exceptionMessage, ['{name}' => $name, '{value}' => $value->toHttpValue()]));
    }

    /**
     * @return array<int>
     */
    public function indices(): array
    {
        return array_keys($this->names());
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
     * @param ?callable(SfType, string): (bool|string) $validate
     *
     * @throws InvalidOffset|Violation
     *
     * @return array{0:string, 1:Item}
     */
    public function getByIndex(int $index, ?callable $validate = null): array
    {
        $foundOffset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);

        $validator = function (Item $value, string $name, int $index, callable $validate): array {
            if (true === ($exceptionMessage = $validate($value->value(), $name))) {
                return [$name, $value];
            }

            if (!is_string($exceptionMessage) || '' === trim($exceptionMessage)) {
                $exceptionMessage = "The parameter at position '{index}' whose name is '{name}' with the value '{value}' failed validation.";
            }

            throw new Violation(strtr($exceptionMessage, ['{index}' => $index, '{name}' => $name, '{value}' => $value->toHttpValue()]));
        };

        foreach ($this as $offset => $pair) {
            if ($offset === $foundOffset) {
                return match ($validate) {
                    null => $pair,
                    default =>  $validator($pair[1], $pair[0], $index, $validate),
                };
            }
        }

        throw InvalidOffset::dueToIndexNotFound($index);
    }

    /**
     * Returns the name associated with the given index or null otherwise.
     */
    public function indexByName(string $name): ?int
    {
        foreach ($this as $index => $member) {
            if ($name === $member[0]) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Returns the index associated with the given name or null otherwise.
     */
    public function nameByIndex(int $index): ?string
    {
        $index = $this->filterIndex($index);
        if (null === $index) {
            return null;
        }

        foreach ($this as $offset => $member) {
            if ($offset === $index) {
                return $member[0];
            }
        }

        return null;
    }

    /**
     * @return array{0:string, 1:Item}|array{}
     */
    public function first(): array
    {
        try {
            return $this->getByIndex(0);
        } catch (InvalidOffset) {
            return [];
        }
    }

    /**
     * @return array{0:string, 1:Item}|array{}
     */
    public function last(): array
    {
        try {
            return $this->getByIndex(-1);
        } catch (InvalidOffset) {
            return [];
        }
    }

    /**
     * Returns true only if the instance only contains the listed keys, false otherwise.
     *
     * @param array<string> $names
     */
    public function allowedNames(array $names): bool
    {
        foreach ($this->members as $name => $member) {
            if (!in_array($name, $names, true)) {
                return false;
            }
        }

        return [] !== $names;
    }

    /**
     * Returns the member value or null if no members value exists.
     *
     * @param ?callable(SfType): (bool|string) $validate
     *
     * @throws Violation if the validation fails
     *
     * @return SfType|null
     */
    public function valueByName(
        string $name,
        ?callable $validate = null,
        bool|string $required = false,
        Bytes|Token|DisplayString|DateTimeImmutable|string|int|float|bool|null $default = null
    ): Bytes|Token|DisplayString|DateTimeImmutable|string|int|float|bool|null {
        if (null !== $default && null === Type::tryFromVariable($default)) {
            throw new SyntaxError('The default parameter is invalid.');
        }

        try {
            return $this->getByName($name, $validate)->value();
        } catch (InvalidOffset $exception) {
            if (false === $required) {
                return $default;
            }

            $message = $required;
            if (!is_string($message) || '' === trim($message)) {
                $message = "The required parameter '{name}' is missing.";
            }

            throw new Violation(strtr($message, ['{name}' => $name]), previous: $exception);
        }
    }

    /**
     * Returns the member value and name as pair or an empty array if no members value exists.
     *
     * @param ?callable(SfType, string): (bool|string) $validate
     * @param array{0:string, 1:SfType}|array{} $default
     *
     * @throws Violation if the validation fails
     *
     * @return array{0:string, 1:SfType}|array{}
     */
    public function valueByIndex(int $index, ?callable $validate = null, bool|string $required = false, array $default = []): array
    {
        $default = match (true) {
            [] === $default => [],
            !array_is_list($default) => throw new SyntaxError('The pair must be represented by an array as a list.'), /* @phpstan-ignore-line */
            2 !== count($default) => throw new SyntaxError('The pair first member is the name; its second member is its value.'), /* @phpstan-ignore-line */
            null === ($name = MapKey::tryFrom($default[0])?->value) => throw new SyntaxError('The pair first member is invalid.'),
            null === ($value = Item::tryNew($default[1])?->value()) => throw new SyntaxError('The pair second member is invalid.'),
            default => [$name, $value],
        };

        try {
            $tuple = $this->getByIndex($index, $validate);

            return [$tuple[0], $tuple[1]->value()];
        } catch (InvalidOffset $exception) {
            if (false === $required) {
                return $default;
            }

            $message = $required;
            if (!is_string($message) || '' === trim($message)) {
                $message = "The required parameter at position '{index}' is missing.";
            }

            throw new Violation(strtr($message, ['{index}' => $index]), previous: $exception);
        }
    }

    /**
     * @param StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|SfType|null $member
     */
    public function add(
        string $name,
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool|null $member
    ): self {
        if (null === $member) {
            return $this;
        }
        $members = $this->members;
        $members[MapKey::from($name)->value] = self::filterMember($member);

        return $this->newInstance($members);
    }

    /**
     * @param array<string, Item> $members
     */
    private function newInstance(array $members): self
    {
        return match(true) {
            $members == $this->members => $this,
            default => new self($members),
        };
    }

    private function remove(string|int ...$names): self
    {
        if ([] === $this->members || [] === $names) {
            return $this;
        }

        $offsets = array_keys($this->members);
        $max = count($offsets);
        $reducer = fn (array $carry, string|int $name): array => match (true) {
            is_string($name) && (false !== ($position = array_search($name, $offsets, true))),
            is_int($name) && (null !== ($position = $this->filterIndex($name, $max))) => [$position => true] + $carry,
            default => $carry,
        };

        $indices = array_reduce($names, $reducer, []);

        return match (true) {
            [] === $indices => $this,
            $max === count($indices) => self::new(),
            default => self::fromPairs((function (array $offsets) {
                foreach ($this as $offset => $pair) {
                    if (!array_key_exists($offset, $offsets)) {
                        yield $pair;
                    }
                }
            })($indices)),
        };
    }

    public function removeByIndices(int ...$indices): self
    {
        return $this->remove(...$indices);
    }

    public function removeByKeys(string ...$names): self
    {
        return $this->remove(...$names);
    }

    /**
     * @param StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|SfType $member
     */
    public function append(
        string $name,
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): self {
        $members = $this->members;
        unset($members[$name]);

        return $this->newInstance([...$members, MapKey::from($name)->value => self::filterMember($member)]);
    }

    /**
     * @param StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|SfType $member
     */
    public function prepend(
        string $name,
        StructuredFieldProvider|OuterList|Dictionary|InnerList|Parameters|Item|Token|Bytes|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): self {
        $members = $this->members;
        unset($members[$name]);

        return $this->newInstance([MapKey::from($name)->value => self::filterMember($member), ...$members]);
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function push(array ...$pairs): self
    {
        return match (true) {
            [] === $pairs => $this,
            default => self::fromPairs((function (iterable $pairs) {
                yield from $this->getIterator();
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
                yield from $this->getIterator();
            })($pairs)),
        };
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$members
     */
    public function insert(int $index, array ...$members): self
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
            })($this->getIterator()),
        };
    }

    /**
     * @param array{0:string, 1:SfItemInput} $pair
     */
    public function replace(int $index, array $pair): self
    {
        $offset = $this->filterIndex($index) ?? throw InvalidOffset::dueToIndexNotFound($index);
        $pair[1] = self::filterMember($pair[1]);
        $pairs = iterator_to_array($this);

        return match (true) {
            $pairs[$offset] == $pair => $this,
            default => self::fromPairs(array_replace($pairs, [$offset => $pair])),
        };
    }

    /**
     * @param StructuredFieldProvider|Dictionary|Parameters|iterable<string, SfItemInput> ...$others
     */
    public function mergeAssociative(StructuredFieldProvider|iterable ...$others): self
    {
        $members = $this->members;
        foreach ($others as $other) {
            if ($other instanceof StructuredFieldProvider) {
                $other = $other->toStructuredField();
                if (!is_iterable($other)) {
                    throw new InvalidArgument('The "'.$other::class.'" instance can not be used for creating a .'.self::class.' structured field.');
                }
            }

            if ($other instanceof self || $other instanceof Dictionary) {
                $other = $other->toAssociative();
            }

            foreach ($other as $name => $value) {
                $members[$name] = $value;
            }
        }

        return new self($members);
    }

    /**
     * @param StructuredFieldProvider|Parameters|Dictionary|iterable<array{0:string, 1:SfItemInput}> ...$others
     */
    public function mergePairs(Dictionary|Parameters|StructuredFieldProvider|iterable ...$others): self
    {
        $members = $this->members;
        foreach ($others as $other) {
            if (!$other instanceof self) {
                $other = self::fromPairs($other);
            }
            foreach ($other->toAssociative() as $name => $value) {
                $members[$name] = $value;
            }
        }

        return new self($members);
    }

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasNames($offset);
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): Item
    {
        return $this->getByName($offset);
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
     * @param callable(array{0:string, 1:Item}, int): TMap $callback
     *
     * @template TMap
     *
     * @return Iterator<TMap>
     */
    public function map(callable $callback): Iterator
    {
        foreach ($this as $offset => $pair) {
            yield ($callback)($pair, $offset);
        }
    }

    /**
     * @param callable(TInitial|null, array{0:string, 1:Item}, int): TInitial $callback
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        foreach ($this as $offset => $pair) {
            $initial = $callback($initial, $pair, $offset);
        }

        return $initial;
    }

    /**
     * @param callable(array{0:string, 1:Item}, int): bool $callback
     */
    public function filter(callable $callback): self
    {
        return self::fromPairs(new CallbackFilterIterator($this->getIterator(), $callback));
    }

    /**
     * @param callable(array{0:string, 1:Item}, array{0:string, 1:Item}): int $callback
     */
    public function sort(callable $callback): self
    {
        $members = iterator_to_array($this);
        uasort($members, $callback);

        return self::fromPairs($members);
    }
}
