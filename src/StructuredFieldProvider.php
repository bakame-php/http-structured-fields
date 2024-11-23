<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @phpstan-type SfType Bytes|Token|DisplayString|DateTimeImmutable|string|int|float|bool
 * @phpstan-type SfTypeInput SfType|DateTimeInterface
 * @phpstan-type SfDataTyoe Dictionary|InnerList|Item|OuterList|Parameters
 * @phpstan-type SfItemInput SfTypeInput|SfDataTyoe|StructuredFieldProvider
 * @phpstan-type SfMemberInput iterable<SfItemInput>|SfItemInput
 * @phpstan-type SfParameterInput iterable<array{0:string, 1?:SfItemInput}>
 * @phpstan-type SfInnerListPair array{0:iterable<SfItemInput>, 1?:Parameters|SfParameterInput}
 * @phpstan-type SfItemPair array{0:SfTypeInput, 1?:Parameters|SfParameterInput}
 */
interface StructuredFieldProvider
{
    /**
     * Returns one of the StructuredField Data Type class.
     */
    public function toStructuredField(): Dictionary|InnerList|Item|OuterList|Parameters;
}
