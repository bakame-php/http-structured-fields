<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Closure;
use Iterator;

/**
 * @template TKey of string
 * @template TValue of StructuredField
 * @template-extends MemberContainer<TKey, TValue>
 */
interface MemberOrderedMap extends MemberContainer
{
    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<int, array{0:TKey, 1:TValue}>
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

    /**
     * Returns the last member of the list if it exists, null otherwise.
     *
     * @return ?array{0:TKey, 1:TValue}
     */
    public function last(): ?array;

    /**
     * Returns the first member of the list if it exists, null otherwise.
     *
     * @return ?array{0:TKey, 1:TValue}
     */
    public function first(): ?array;

    /**
     * Inserts pairs at the end of the container.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param array{0:TKey, 1:TValue} ...$pairs
     */
    public function push(array ...$pairs): static;

    /**
     * Inserts pairs at the beginning of the container.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param array{0:TKey, 1:TValue} ...$pairs
     */
    public function unshift(array ...$pairs): static;

    /**
     * Deletes members associated with the list using the member pair offset.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function removeByIndices(int ...$indices): static;

    /**
     * Deletes members associated with the list using the member key.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function removeByKeys(string ...$keys): static;

    /**
     * Insert a member pair using its offset.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param array{0:TKey, 1:TValue} ...$members
     */
    public function insert(int $index, array ...$members): static;

    /**
     * Replace a member pair using its offset.
     *
     *  This method MUST retain the state of the current instance, and return
     *  an instance that contains the specified changes.
     *
     * @param array{0:TKey, 1:TValue} $pair
     */
    public function replace(int $index, array $pair): static;

    /**
     * Sort a container by value using a callback.
     *
     * @param Closure(array{0:TKey, 1:TValue}, array{0:TKey, 1:TValue}): int $callback
     */
    public function sort(Closure $callback): static;
}
