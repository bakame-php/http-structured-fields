<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<array-key, Item|InnerList>
 */
final class Dictionary implements Countable, IteratorAggregate, StructuredField
{
    private function __construct(
        /** @var array<string, Item|InnerList>  */
        private array $elements = []
    ) {
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<string, InnerList|Item|ByteSequence|Token|bool|int|float|string> $elements
     */
    public static function fromAssociative(iterable $elements = []): self
    {
        $instance = new self();
        foreach ($elements as $index => $element) {
            $instance->set($index, $element);
        }

        return $instance;
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each element is composed of an array with two elements
     * the first element represents the instance entry key
     * the second element represents the instance entry value
     *
     * @param iterable<array{0:string, 1:InnerList|Item|ByteSequence|Token|bool|int|float|string}> $pairs
     */
    public static function fromPairs(iterable $pairs = []): self
    {
        $instance = new self();
        foreach ($pairs as [$key, $element]) {
            $instance->set($key, $element);
        }

        return $instance;
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.2
     */
    public static function fromHttpValue(string $httpValue): self
    {
        return Parser::parseDictionary($httpValue);
    }

    public function toHttpValue(): string
    {
        $returnValue = [];
        foreach ($this->elements as $key => $element) {
            $returnValue[] = match (true) {
                $element instanceof Item && true === $element->value() => $key.$element->parameters()->toHttpValue(),
                default => $key.'='.$element->toHttpValue(),
            };
        }

        return implode(', ', $returnValue);
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
     * @return Iterator<string, Item|InnerList>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $index => $element) {
            yield $index => $element;
        }
    }

    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:string, 1:Item|InnerList}>
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

    public function get(string $key): Item|InnerList
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
     * @return array{0:string, 1:Item|InnerList}
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

    /**
     * Add an element at the end of the instance if the key is new otherwise update the value associated with the key.
     */
    public function set(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        self::validateKey($key);

        $this->elements[$key] = self::filterElement($element);
    }

    /**
     * Validate the instance key against RFC8941 rules.
     */
    private static function validateKey(string $key): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError("The dictionary key `$key` contains invalid characters.");
        }
    }

    private static function filterElement(InnerList|Item|ByteSequence|Token|bool|int|float|string $element): InnerList|Item
    {
        return match (true) {
            $element instanceof InnerList, $element instanceof Item => $element,
            default => Item::from($element),
        };
    }

    /**
     * Delete elements associated with the list of submitted keys.
     */
    public function delete(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->elements[$key]);
        }
    }

    /**
     * Remove all elements from the instance.
     */
    public function clear(): void
    {
        $this->elements = [];
    }

    /**
     * Add an element at the end of the instance if the key is new delete any previous reference to the key.
     */
    public function append(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        self::validateKey($key);

        unset($this->elements[$key]);

        $this->elements[$key] = self::filterElement($element);
    }

    /**
     * Add an element at the beginning of the instance if the key is new delete any previous reference to the key.
     */
    public function prepend(string $key, InnerList|Item|ByteSequence|Token|bool|int|float|string $element): void
    {
        self::validateKey($key);

        unset($this->elements[$key]);

        $this->elements = [...[$key => self::filterElement($element)], ...$this->elements];
    }

    /**
     * Merge multiple instances.
     */
    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            $this->elements = [...$this->elements, ...$other->elements];
        }
    }
}
