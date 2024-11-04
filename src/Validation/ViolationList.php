<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use ArrayAccess;
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
     * @param string|int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param string|int $offset
     *
     * @return Violation
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @param string|int $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->errors[$offset]);
    }

    /**
     * @param string|int|null $offset
     * @param Violation $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (null === $offset) {
            throw new TypeError('null can not be used as a valid offset value.');
        }
        $this->add($offset, $value);
    }

    public function has(string|int $offset): bool
    {
        if (is_int($offset)) {
            return null !== $this->filterIndex($offset);
        }

        return array_key_exists($offset, $this->errors);
    }

    public function get(string|int $offset): Violation
    {
        return $this->errors[$this->filterIndex($offset) ?? throw InvalidOffset::dueToIndexNotFound($offset)];
    }

    public function add(string|int $offset, Violation $error): void
    {
        $this->errors[$offset] = $error;
    }

    /**
     * @param iterable<array-key, Violation> $errors
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
