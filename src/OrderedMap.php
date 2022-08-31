<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends Container<TKey, TValue>
 */
interface OrderedMap extends Container
{
    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:TKey, 1:TValue}>
     */
    public function toPairs(): Iterator;

    /**
     * Tells whether an item or an inner-list and a key are attached to the given index position.
     */
    public function hasPair(int $index): bool;

    /**
     * Returns the item or the inner-list and its key as attached to the given
     * collection according to their index position otherwise throw.
     *
     * @throws InvalidOffset If the key is not found
     *
     * @return array{0:TKey, 1:TValue}
     */
    public function pair(int $index): array;

    /**
     * Returns an ordered list of the instance keys.
     *
     * @return array<TKey>
     */
    public function keys(): array;

    /**
     * Adds a member at the end of the instance otherwise updates the value associated with the key if already present.
     *
     * @param TValue $member
     *
     * @throws SyntaxError If the string key is not a valid
     *
     * @return OrderedMap<TKey, TValue>
     */
    public function set(string $key, StructuredField $member): self;

    /**
     * Deletes members associated with the list of submitted keys.
     *
     * @return OrderedMap<TKey, TValue>
     */
    public function delete(string ...$keys): self;

    /**
     * Adds a member at the end of the instance and deletes any previous reference to the key if present.
     *
     * @param TValue $member
     *
     * @throws SyntaxError If the string key is not a valid
     *
     * @return OrderedMap<TKey, TValue>
     */
    public function append(string $key, StructuredField $member): self;

    /**
     * Adds a member at the beginning of the instance and deletes any previous reference to the key if present.
     *
     * @param TValue $member
     *
     * @throws SyntaxError If the string key is not a valid
     *
     * @return OrderedMap<TKey, TValue>
     */
    public function prepend(string $key, StructuredField $member): self;

    /**
     * Merges multiple instances using iterable associative structures.
     *
     * @param iterable<TKey, TValue> ...$others
     *
     * @return OrderedMap<TKey, TValue>
     */
    public function mergeAssociative(iterable ...$others): self;

    /**
     * Merges multiple instances using iterable pairs.
     *
     * @param OrderedMap<TKey, TValue>|iterable<array{0:TKey, 1:TValue}> ...$others
     *
     * @return OrderedMap<TKey, TValue>
     */
    public function mergePairs(OrderedMap|iterable ...$others): self;
}
