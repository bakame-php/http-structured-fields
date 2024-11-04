<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use ArrayAccess;
use Bakame\Http\StructuredFields\ForbiddenOperation;
use Bakame\Http\StructuredFields\InvalidOffset;
use Bakame\Http\StructuredFields\StructuredField;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @phpstan-import-type SfType from StructuredField
 *
 * @implements ArrayAccess<array-key, array{0:string, 1:SfType}|array{}|SfType|null>
 * @implements IteratorAggregate<array-key, array{0:string, 1:SfType}|array{}|SfType|null>
 */
final class ParsedParameters implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @param array<array-key, array{0:string, 1:SfType}|array{}|SfType|null> $parameters
     */
    public function __construct(
        public readonly array $parameters = [],
    ) {
    }

    public function count(): int
    {
        return count($this->parameters);
    }

    public function getIterator(): Iterator
    {
        yield from $this->parameters;
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->parameters);
    }

    public function offsetGet($offset): mixed
    {
        return $this->offsetExists($offset) ? $this->parameters[$offset] : throw InvalidOffset::dueToMemberNotFound($offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }
}
