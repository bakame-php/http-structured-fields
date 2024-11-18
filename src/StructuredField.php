<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use Stringable;

/**
 * @internal The interface MUST not be implemented outside the package codebase
 *
 * @phpstan-type SfType ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool
 * @phpstan-type SfTypeInput SfType|DateTimeInterface
 * @phpstan-type SfItemInput Item|SfTypeInput|StructuredFieldProvider|StructuredField
 * @phpstan-type SfMemberInput iterable<SfItemInput>|SfItemInput
 * @phpstan-type SfParameterInput iterable<array{0:string, 1?:SfItemInput}>
 * @phpstan-type SfInnerListPair array{0:iterable<SfItemInput>, 1?:Parameters|SfParameterInput}
 * @phpstan-type SfItemPair array{0:SfTypeInput, 1?:Parameters|SfParameterInput}
 */
interface StructuredField extends Stringable
{
    /**
     * Returns a new instance from an HTTP Header or Trailer value string in compliance to an accepted RFC.
     *
     * @throws StructuredFieldError If the HTTP value can not be parsed
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): self;

    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     *
     * @throws StructuredFieldError If the object can not be serialized
     */
    public function toHttpValue(?Ietf $rfc = null): string;

    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value
     * using the last accepted RFC protocol.
     *
     * @throws StructuredFieldError If the object can not be serialized
     */
    public function __toString(): string;
}
