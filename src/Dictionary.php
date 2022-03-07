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

        foreach (explode(',', $field) as $element) {
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

            $instance->set($found['key'], self::parseItemOrInnerList($found['value']));
        }

        return $instance;
    }

    private static function parseItemOrInnerList(string $element): Item|InnerList
    {
        if (str_starts_with($element, '(')) {
            return InnerList::fromField($element);
        }

        return Item::fromField($element);
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

    public function findByKey(string $key): Item|InnerList|null
    {
        return $this->elements[$key] ?? null;
    }

    public function findByIndex(int $index): Item|InnerList|null
    {
        return array_values($this->elements)[$index] ?? null;
    }

    public function keyExists(string $key): bool
    {
        return array_key_exists($key, $this->elements);
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

    public function unset(string ...$indexes): void
    {
        foreach ($indexes as $index) {
            unset($this->elements[$index]);
        }
    }

    public function set(string $index, Item|InnerList $element): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $index)) {
            throw new SyntaxError('Invalid characters in key: `'.$index.'`');
        }

        $this->elements[$index] = $element;
    }
}
