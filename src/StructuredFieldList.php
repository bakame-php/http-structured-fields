<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends StructuredFieldContainer<TKey, TValue>
 */
interface StructuredFieldList extends StructuredFieldContainer
{
    /**
     * Insert members at the beginning of the list.
     *
     * @param TValue ...$members
     *
     * @return StructuredFieldList<TKey, TValue>
     */
    public function unshift(StructuredField ...$members): self;

    /**
     * Insert members at the end of the list.
     *
     * @param TValue ...$members
     *
     * @return StructuredFieldList<TKey, TValue>
     */
    public function push(StructuredField ...$members): self;

    /**
     * Replace the member associated with the index.
     *
     * @param TValue ...$members
     *
     * @throws InvalidOffset If the index does not exist
     *
     * @return StructuredFieldList<TKey, TValue>
     */
    public function insert(int $index, StructuredField ...$members): self;

    /**
     * @param TValue $member
     *
     * @return StructuredFieldList<TKey, TValue>
     */
    public function replace(int $index, StructuredField $member): self;

    /**
     * Delete members associated with the list of instance indexes.
     *
     * @return StructuredFieldList<TKey, TValue>
     */
    public function remove(int ...$indexes): self;
}
