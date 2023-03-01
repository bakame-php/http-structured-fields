<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends MemberContainer<TKey, TValue>
 */
interface MemberOrderedMap extends MemberContainer
{
    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:TKey, 1:TValue}>
     */
    public function toPairs(): Iterator;

    /**
     * Tells whether a pair is attached to the given index position.
     */
    public function hasPair(int ...$indexes): bool;

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
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function add(string $key, StructuredField $member): static;

    /**
     * Adds a member at the end of the instance and deletes any previous reference to the key if present.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function append(string $key, StructuredField $member): static;

    /**
     * Adds a member at the beginning of the instance and deletes any previous reference to the key if present.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function prepend(string $key, StructuredField $member): static;

    /**
     * Merges multiple instances using iterable associative structures.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param iterable<TKey, TValue> ...$others
     */
    public function mergeAssociative(iterable ...$others): static;

    /**
     * Merges multiple instances using iterable pairs.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param iterable<array{0:TKey, 1:TValue}> ...$others
     */
    public function mergePairs(iterable ...$others): static;
}
