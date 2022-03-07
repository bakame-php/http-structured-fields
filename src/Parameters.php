<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use Iterator;

/**
 * @implements StructuredFieldContainer<string, Item>
 */
final class Parameters implements StructuredFieldContainer
{
    /** @var array<array-key, Item> */
    private array $elements;

    /**
     * @param iterable<string, Item> $elements
     */
    public function __construct(iterable $elements = [])
    {
        $this->elements = [];
        foreach ($elements as $key => $value) {
            $this->set($key, $value);
        }
    }

    public static function fromField(string $field): self
    {
        $instance = new self();
        if ('' === $field) {
            return $instance;
        }

        $parameters = new self();
        foreach (explode(';', $field) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => '?1'];
            if (rtrim($key) !== $key || ltrim($value) !== $value) {
                throw new SyntaxError("Invalid parameter pair: `$field`.");
            }

            $key = trim($key);
            if ('' !== $key) {
                $parameters->set($key, Item::fromField($value));
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

    public function findByKey(string $key): Item|null
    {
        return $this->elements[$key] ?? null;
    }

    public function findByIndex(int $index): Item|null
    {
        return array_values($this->elements)[$index] ?? null;
    }

    public function indexExist(string $index): bool
    {
        return array_key_exists($index, $this->elements);
    }

    public function canonical(): string
    {
        $returnValue = '';

        foreach ($this->elements as $key => $val) {
            $returnValue .= ';'.$key;
            if ($val->value() !== true) {
                $returnValue .= '='.$val->canonical();
            }
        }

        return $returnValue;
    }

    public function unset(string ...$indexes): void
    {
        foreach ($indexes as $index) {
            unset($this->elements[$index]);
        }
    }

    public function set(string $index, Item $value): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $index)) {
            throw new SyntaxError("Invalid characters in key: $index");
        }

        if (!$value->parameters()->isEmpty()) {
            throw new SyntaxError('the Item cannot be parameterized.');
        }

        $this->elements[$index] = $value;
    }
}
