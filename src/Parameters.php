<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

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
            $this->append($key, $value);
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

    public function keys(): array
    {
        return array_keys($this->elements);
    }

    public function getByKey(string $key): Item|InnerList|null
    {
        return $this->elements[$key] ?? null;
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function getByIndex(int $index): Item|InnerList|null
    {
        return array_values($this->elements)[$this->filterIndex($index)] ?? null;
    }

    public function hasIndex(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

    public function toField(): string
    {
        $returnValue = '';

        foreach ($this->elements as $key => $val) {
            $returnValue .= ';'.$key;
            if ($val->value() !== true) {
                $returnValue .= '='.$val->toField();
            }
        }

        return $returnValue;
    }

    public function set(string $key, Item $element): void
    {
        $this->filterKey($key);
        $this->filterItem($element);

        $this->elements[$key] = $element;
    }

    public function delete(string ...$indexes): void
    {
        foreach ($indexes as $index) {
            unset($this->elements[$index]);
        }
    }

    public function append(string $key, Item $element): void
    {
        $this->filterKey($key);
        $this->filterItem($element);
        unset($this->elements[$key]);

        $this->elements[$key] = $element;
    }

    public function prepend(string $key, Item $element): void
    {
        $this->filterKey($key);
        $this->filterItem($element);
        unset($this->elements[$key]);

        $this->elements = [...[$key => $element], ...$this->elements];
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

    private function filterKey(string $key): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError('Invalid characters in key: `'.$key.'`');
        }
    }

    private function filterItem(Item $item): void
    {
        if (!$item->parameters()->isEmpty()) {
            throw new SyntaxError('the Item cannot be parameterized.');
        }
    }
}
