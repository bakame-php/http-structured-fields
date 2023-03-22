<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-type SfTypeInput ByteSequence|Token|\DateTimeInterface|\Stringable|string|int|float|bool
 * @phpstan-type SfItem ValueAccess&ParameterAccess
 * @phpstan-type SfItemInput SfItem|SfTypeInput
 * @phpstan-type SfMember (MemberList<int, SfItem>|ValueAccess)&ParameterAccess
 * @phpstan-type SfMemberInput iterable<SfItemInput>|SfItemInput
 */
interface StructuredField extends Stringable
{
    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     */
    public function toHttpValue(): string;
}
