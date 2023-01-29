<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends IteratorAggregate<TKey, TValue>
 */
interface MemberContainer extends Countable, IteratorAggregate, StructuredField
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
     * @return Iterator<TKey, TValue>
     */
    public function getIterator(): Iterator;

    /**
     * @return TValue
     */
    public function get(string|int $offset): StructuredField;

    public function has(string|int $offset): bool;
}
