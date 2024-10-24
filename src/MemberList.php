<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Closure;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends MemberContainer<TKey, TValue>
 */
interface MemberList extends MemberContainer
{
    /**
     * Inserts members at the beginning of the list.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function unshift(StructuredField ...$members): static;

    /**
     * Inserts members at the end of the list.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function push(StructuredField ...$members): static;

    /**
     * Inserts members at the index.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $key, StructuredField ...$members): static;

    /**
     * Replaces the member associated with the index.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $key, StructuredField $member): static;

    /**
     * Returns the last member of the list if it exists, null otherwise.
     *
     * @return ?TValue
     */
    public function last(): ?StructuredField;

    /**
     * Returns the first member of the list if it exists, null otherwise.
     *
     * @return ?TValue
     */
    public function first(): ?StructuredField;

    /**
     * Sort a container by value using a callback.
     *
     * @param Closure(TValue, TValue): int $callback
     */
    public function sort(Closure $callback): static;
}
