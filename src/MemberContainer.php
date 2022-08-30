<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue of StructuredField
 * @template-extends IteratorAggregate<TKey, TValue>
 * @template-extends ArrayAccess<TKey, TValue>
 */
interface MemberContainer extends Countable, ArrayAccess, IteratorAggregate, StructuredField
{
    /**
     * Tells whether the instance has no member.
     */
    public function hasNoMembers(): bool;

    /**
     * Tells whether the instance contains members.
     */
    public function hasMembers(): bool;

    /**
     * Remove all members from the instance.
     *
     * @return self<TKey, TValue>
     */
    public function clear(): self;

    /**
     * @return Iterator<TKey, TValue>
     */
    public function getIterator(): Iterator;

    /**
     * @return TValue
     */
    public function get(string|int $offset): StructuredField;

    public function has(string|int $offset): bool;

    /**
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool;

    /**
     * @param TKey $offset
     */
    public function offsetGet(mixed $offset): mixed;

    /**
     * @param TKey|null $offset
     * @param TValue $value
     */
    public function offsetSet(mixed $offset, mixed $value): void;

    /**
     * @param TKey $offset
     */
    public function offsetUnset(mixed $offset): void;

    /**
     * @return array<TKey, MemberContainer<int,Item>|ByteSequence|Token|string|int|float|bool>
     */
    public function values(): array;

    /**
     * @return MemberContainer<int,Item>|string|int|float|bool|null
     */
    public function value(string|int $offset): MemberContainer|float|int|bool|string|null;
}
