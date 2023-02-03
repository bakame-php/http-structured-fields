<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

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
    public function insert(int $index, StructuredField ...$members): static;

    /**
     * Replaces the member associated with the index.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function replace(int $index, StructuredField $member): static;

    /**
     * Deletes members associated with the list of submitted keys.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     */
    public function remove(int ...$indexes): static;
}
