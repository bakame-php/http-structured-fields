<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use Iterator;

/**
 * @implements StructuredFieldContainer<array-key, Item|InnerList>
 */
final class Dictionary implements StructuredFieldContainer
{
    /** @var array<string, Item|InnerList>  */
    private array $elements;

    /**
     * @param iterable<string, Item|InnerList> $elements
     */
    public function __construct(iterable $elements = [])
    {
        $this->elements = [];
        foreach ($elements as $index => $element) {
            $this->set($index, $element);
        }
    }

    public static function fromField(string $field): self
    {
        $instance = new self();
        $field = trim($field, ' ');
        if ('' === $field) {
            return $instance;
        }

        if (1 === preg_match("/[^\x20-\x7E\t]/", $field) || str_starts_with($field, "\t")) {
            throw new SyntaxError("Invalid dictionary field: `$field`.");
        }

        $parser = fn (string $element): Item|InnerList => str_starts_with($element, '(')
            ? InnerList::fromField($element)
            : Item::fromField($element);

        return array_reduce(explode(',', $field), function (self $instance, string $element) use ($parser): self {
            [$key, $value] = self::extractPair($element);

            $instance->set($key, $parser($value));

            return $instance;
        }, $instance);
    }

    /**
     * @throws SyntaxError
     *
     * @return array{0:string, 1:string}
     */
    private static function extractPair(string $element): array
    {
        $element = trim($element);

        if ('' === $element) {
            throw new SyntaxError("dictionary pair can not be empty: `$element`.");
        }

        if (1 !== preg_match('/^(?<key>[a-z*][a-z0-9.*_-]*)(=)?(?<value>.*)/', $element, $found)) {
            throw new SyntaxError("Invalid dictionary pair: `$element`.");
        }

        if (rtrim($found['key']) !== $found['key'] || ltrim($found['value']) !== $found['value']) {
            throw new SyntaxError("Invalid dictionary pair: `$element`.");
        }

        $found['value'] = trim($found['value']);
        if ('' === $found['value'] || str_starts_with($found['value'], ';')) {
            $found['value'] = '?1'.$found['value'];
        }

        return [$found['key'], $found['value']];
    }

    public function isEmpty(): bool
    {
        return [] === $this->elements;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * @return Iterator<string, Item|InnerList>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $index => $element) {
            yield $index => $element;
        }
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->elements);
    }

    public function getByKey(string $key): Item|InnerList|null
    {
        if (!array_key_exists($key, $this->elements)) {
            throw new InvalidIndex('No element exists with the key `'.$key.'`.');
        }

        return $this->elements[$key];
    }

    public function hasIndex(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

    public function getByIndex(int $index): Item|InnerList|null
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw new InvalidIndex('No element exists with the index `'.$index.'`.');
        }

        return array_values($this->elements)[$offset];
    }

    public function canonical(): string
    {
        $returnValue = [];
        foreach ($this->elements as $index => $element) {
            $returnValue[] = match (true) {
                $element->value() === true => $index.$element->parameters()->canonical(),
                default => $index.'='.$element->canonical(),
            };
        }

        return implode(', ', $returnValue);
    }

    public function set(string $key, Item|InnerList $element): void
    {
        $this->filterKey($key);

        $this->elements[$key] = $element;
    }

    public function delete(string ...$indexes): void
    {
        foreach ($indexes as $index) {
            unset($this->elements[$index]);
        }
    }

    public function append(string $key, Item|InnerList $element): void
    {
        $this->filterKey($key);

        unset($this->elements[$key]);

        $this->elements[$key] = $element;
    }

    public function prepend(string $key, Item|InnerList $element): void
    {
        $this->filterKey($key);

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
}
