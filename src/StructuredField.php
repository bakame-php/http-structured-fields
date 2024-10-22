<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use Stringable;

/**
 * @phpstan-type SfType ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool
 * @phpstan-type SfTypeInput SfType|DateTimeInterface
 * @phpstan-type SfItemInput Item|SfTypeInput|StructuredFieldProvider|StructuredField
 * @phpstan-type SfMemberInput iterable<SfItemInput>|SfItemInput
 * @phpstan-type SfInnerListPair array{0:iterable<SfItemInput>, 1:Parameters|iterable<array{0:string, 1:SfItemInput}>}
 * @phpstan-type SfItemPair array{0:SfTypeInput, 1:Parameters|iterable<array{0:string, 1:SfItemInput}>}
 */
interface StructuredField extends Stringable
{
    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     */
    public function toHttpValue(?Ietf $rfc = null): string;
}
