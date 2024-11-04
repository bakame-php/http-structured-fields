<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use Bakame\Http\StructuredFields\ByteSequence;
use Bakame\Http\StructuredFields\DisplayString;
use Bakame\Http\StructuredFields\StructuredField;
use Bakame\Http\StructuredFields\Token;
use DateTimeImmutable;

/**
 * @phpstan-import-type SfItemInput from StructuredField
 * @phpstan-import-type SfType from StructuredField
 */
final class ParsedItem
{
    /**
     * @param array<array-key, array{0:string, 1:SfType}|array{}|SfType|null> $parameters
     */
    public function __construct(
        public readonly ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool|null $value,
        public readonly array $parameters = [],
        public readonly ViolationList $errors = new ViolationList(),
    ) {
    }
}
