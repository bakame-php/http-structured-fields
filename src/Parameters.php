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
    private function __construct(
        /** @var array<array-key, Item> */
        private array $elements = []
    ) {
    }

    /**
     * @param iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> $elements
     */
    public static function fromAssociative(iterable $elements = []): self
    {
        $instance = new self();
        foreach ($elements as $key => $element) {
            $instance->set($key, $element);
        }

        return $instance;
    }

    /**
     * @param iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> $pairs
     */
    public static function fromPairs(iterable $pairs = []): self
    {
        $instance = new self();
        foreach ($pairs as [$key, $element]) {
            $instance->set($key, $element);
        }

        return $instance;
    }

    public static function fromHttpValue(string $httpValue): self
    {
        $instance = new self();
        if ('' === $httpValue) {
            return $instance;
        }

        foreach (explode(';', $httpValue) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => '?1'];
            if (rtrim($key) !== $key || ltrim($value) !== $value) {
                throw new SyntaxError("The HTTP textual representation `$pair` for a parameter pair contains invalid characters.");
            }

            $key = trim($key);
            if ('' !== $key) {
                $instance->set($key, Item::fromHttpValue($value));
            }
        }

        return $instance;
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
     * @return Iterator<array{0:string, 1:Item}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->elements as $index => $element) {
            yield [$index, $element];
        }
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->elements);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function get(string $key): Item
    {
        if (!array_key_exists($key, $this->elements)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->elements[$key];
    }

    public function hasPair(int $index): bool
    {
        return null !== $this->filterIndex($index);
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

    /**
     * @return array{0:string, 1:Item}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return [
            array_keys($this->elements)[$offset],
            array_values($this->elements)[$offset],
        ];
    }

    public function set(string $key, Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        $element = self::filterElement($element);
        self::validate($key, $element);

        $this->elements[$key] = $element;
    }

    private static function filterElement(Item|ByteSequence|Token|bool|int|float|string $element): Item
    {
        return match (true) {
            $element instanceof Item => $element,
            default => Item::from($element),
        };
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

    public function delete(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->elements[$key]);
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
