<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use ArrayAccess;
use BackedEnum;
use Bakame\Http\StructuredFields\InvalidOffset;
use Countable;
use Iterator;
use IteratorAggregate;
use Stringable;
use TypeError;

use function array_filter;
use function array_map;
use function count;
use function implode;
use function is_int;
use function is_string;

use const ARRAY_FILTER_USE_BOTH;

/**
 * @implements IteratorAggregate<array-key,Violation>
 * @implements ArrayAccess<array-key,Violation>
 */
final class ViolationList implements IteratorAggregate, Countable, ArrayAccess, Stringable
{
    /** @var array<Violation> */
    private array $errors = [];

    /**
     * @param iterable<array-key, Violation> $errors
     */
    public function __construct(iterable $errors = [])
    {
        $this->addAll($errors);
    }

    public function count(): int
    {
        return count($this->errors);
    }

    public function getIterator(): Iterator
    {
        yield from $this->errors;
    }

    public function __toString(): string
    {
        return implode(PHP_EOL, array_map(fn (Violation $e): string => $e->getMessage(), $this->errors));
    }

    /**
     * @return array<array-key, string>
     */
    public function summary(): array
    {
        return array_map(fn (Violation $e): string => $e->getMessage(), $this->errors);
    }

    public function hasNoError(): bool
    {
        return [] === $this->errors;
    }

    public function hasErrors(): bool
    {
        return ! $this->hasNoError();
    }

    /**
     * @param array-key $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param array-key $offset
     *
     * @return Violation
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @param array-key $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->errors[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_int($offset) && !is_string($offset)) {
            throw new TypeError('The submitted offset must be an integer or a string');
        }

        if (!$value instanceof Violation) { /* @phpstan-ignore-line */
            throw new TypeError('only '.Violation::class.' instances can be added to the collection,');
        }

        $this->errors[$offset] = $value;
    }

    public function has(BackedEnum|string|int $offset): bool
    {
        if ($offset instanceof BackedEnum) {
            $offset = $offset->value;
        }

        return null !== $this->filterIndex($offset);
    }

    public function get(BackedEnum|string|int $offset): Violation
    {
        if ($offset instanceof BackedEnum) {
            $offset = $offset->value;
        }

        return $this->errors[$this->filterIndex($offset) ?? throw InvalidOffset::dueToIndexNotFound($offset)];
    }

    public function add(BackedEnum|string|int $offset, Violation $error): void
    {
        if ($offset instanceof BackedEnum) {
            $offset = $offset->value;
        }

        $this->offsetSet($offset, $error);
    }

    /**
     * @param iterable<array-key|BackedEnum, Violation> $errors
     */
    public function addAll(iterable $errors): void
    {
        foreach ($errors as $offset => $error) {
            $this->add($offset, $error);
        }
    }

    private function filterIndex(string|int $index, int|null $max = null): string|int|null
    {
        if (!is_int($index)) {
            return $index;
        }

        $max ??= count($this->errors);

        return match (true) {
            [] === $this->errors,
            0 > $max + $index,
            0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    /**
     * @param callable(Violation, array-key): bool $callback
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->errors, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function toException(): Violation
    {
        return new Violation((string) $this);
    }
}
