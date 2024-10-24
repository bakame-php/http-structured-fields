<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Closure;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends ArrayAccess<TKey, TValue>
 * @template-extends IteratorAggregate<TKey, TValue>
 */
interface MemberContainer extends ArrayAccess, Countable, IteratorAggregate, StructuredField
{
    /**
     * Tells whether the instance contains no members.
     */
    public function hasNoMembers(): bool;

    /**
     * Tells whether the instance contains members.
     */
    public function hasMembers(): bool;

    /**
     * Returns an ordered list of the instance keys.
     *
     * @return array<TKey>
     */
    public function keys(): array;

    /**
     * @return TValue
     */
    public function get(string|int $key): StructuredField;

    /**
     * Tells whether the instance contain a members at the specified offsets.
     */
    public function has(string|int ...$keys): bool;

    /**
     * Deletes members associated with the list of submitted keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function remove(string|int ...$keys): static;

    /**
     * Run a filter over each container members.
     *
     * @param Closure(TValue, TKey): bool $callback
     */
    public function filter(Closure $callback): static;

    /**
     * Run a map over each container members.
     *
     * @template TMap
     *
     * @param Closure(TValue, TKey): TMap $callback
     *
     * @return Iterator<TMap>
     */
    public function map(Closure $callback): Iterator;

    /**
     * Iteratively reduce the container to a single value using a callback.
     *
     * @param Closure(TInitial|null, TValue, TKey=): TInitial $callback
     * @param TInitial|null $initial
     *
     * @template TInitial
     *
     * @return TInitial|null
     */
    public function reduce(Closure $callback, mixed $initial = null): mixed;
}
