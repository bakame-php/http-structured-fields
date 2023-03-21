<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-type DataType ByteSequence|Token|\DateTimeInterface|\Stringable|string|int|float|bool
 * @phpstan-type ItemValue ValueAccess&ParameterAccess
 * @phpstan-type ItemStruct ItemValue|DataType
 * @phpstan-type ListMember (MemberList<int, ItemValue>|ValueAccess)&ParameterAccess
 * @phpstan-type PseudoListMember iterable<ItemStruct>|ItemStruct
 */
interface StructuredField extends Stringable
{
    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     */
    public function toHttpValue(): string;
}
