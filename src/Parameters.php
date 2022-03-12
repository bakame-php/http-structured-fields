<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string, Item>
 */
final class Parameters implements Countable, IteratorAggregate, StructuredField
{
    /** @var array<array-key, Item> */
    private array $elements;

    /**
     * @param iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> $elements
     */
    public function __construct(iterable $elements = [])
    {
        $this->elements = [];
        foreach ($elements as $key => $element) {
            $this->set($key, self::filterElement($element));
        }
    }

    private static function filterElement(Item|ByteSequence|Token|bool|int|float|string $element): Item
    {
        return match (true) {
            $element instanceof Item => $element,
            default => Item::from($element),
        };
    }

    public static function fromHttpValue(string $httpValue): self
    {
        $parameters = new self();
        if ('' === $httpValue) {
            return $parameters;
        }

        foreach (explode(';', $httpValue) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => '?1'];
            if (rtrim($key) !== $key || ltrim($value) !== $value) {
                throw new SyntaxError("The HTTP textual representation `$pair` for a parameter pair contains invalid characters.");
            }

            $key = trim($key);
            if ('' !== $key) {
                $parameters->set($key, Item::fromHttpValue($value));
            }
        }

        return $parameters;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    /**
     * @return Iterator<string, Item>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->elements);
    }

    public function getByKey(string $key): Item
    {
        if (!array_key_exists($key, $this->elements)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->elements[$key];
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function getByIndex(int $index): Item
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return array_values($this->elements)[$offset];
    }

    private function filterIndex(int $index): int|null
    {
        $max = count($this->elements);

        return match (true) {
            [] === $this->elements, 0 > $max + $index, 0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    public function hasIndex(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

    public function toHttpValue(): string
    {
        $returnValue = [];

        foreach ($this->elements as $key => $val) {
            if (!$val->parameters()->isEmpty()) {
                throw new SerializationError('Parameters instances can not contain parameterized Items.');
            }

            $value = ';'.$key;
            if ($val->value() !== true) {
                $value .= '='.$val->toHttpValue();
            }

            $returnValue[] = $value;
        }

        return implode('', $returnValue);
    }

    public function set(string $key, Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        $element = self::filterElement($element);
        self::validate($key, $element);

        $this->elements[$key] = $element;
    }

    private static function validate(string $key, Item $item): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError("The Parameters key `$key` contains invalid characters.");
        }

        if (!$item->parameters()->isEmpty()) {
            throw new SyntaxError('Parameters instances can not contain parameterized Items.');
        }
    }

    public function delete(string ...$indexes): void
    {
        foreach ($indexes as $index) {
            unset($this->elements[$index]);
        }
    }

    public function append(string $key, Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        $element = self::filterElement($element);
        self::validate($key, $element);

        unset($this->elements[$key]);

        $this->elements[$key] = $element;
    }

    public function prepend(string $key, Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        $element = self::filterElement($element);
        self::validate($key, $element);

        unset($this->elements[$key]);

        $this->elements = [...[$key => $element], ...$this->elements];
    }

    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            foreach ($other as $key => $value) {
                $this->set($key, $value);
            }
        }
    }
}
