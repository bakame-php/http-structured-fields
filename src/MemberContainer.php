<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Countable;
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
     * @return TValue
     */
    public function get(string|int $offset): StructuredField;

    /**
     * Tells whether the instance contain a members at the specified offset.
     */
    public function has(string|int $offset): bool;
}
