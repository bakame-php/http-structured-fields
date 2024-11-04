<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use ArrayAccess;
use BackedEnum;
use Bakame\Http\StructuredFields\ForbiddenOperation;
use Bakame\Http\StructuredFields\InvalidOffset;
use Bakame\Http\StructuredFields\StructuredField;

/**
 * @phpstan-import-type SfType from StructuredField
 * @implements ArrayAccess<BackedEnum|array-key, array{0:string, 1:SfType}|array{}|SfType|null>
 */
final class ParsedParameters implements ArrayAccess
{
    /**
     * @param array<array-key, array{0:string, 1:SfType}|array{}|SfType|null> $parameters
     */
    public function __construct(
        private readonly array $parameters = [],
        public readonly ViolationList $errors = new ViolationList(),
    ) {
    }

    /**
     * @return array<array-key, array{0:string, 1:SfType}|array{}|SfType|null>
     */
    public function values(): array
    {
        return $this->parameters;
    }

    /**
     * @param BackedEnum|array-key $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists(($offset instanceof BackedEnum ? $offset->value : $offset), $this->parameters);
    }

    /**
     * @param BackedEnum|array-key $offset
     *
     * @throws InvalidOffset If no parameter exists for the selected offset.
     *
     * @return array{0:string, 1:SfType}|array{}|SfType|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($offset instanceof BackedEnum) {
            $offset = $offset->value;
        }

        if (array_key_exists($offset, $this->parameters)) {
            return $this->parameters[$offset];
        }

        throw InvalidOffset::dueToMemberNotFound($offset);
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
