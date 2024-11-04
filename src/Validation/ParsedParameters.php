<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use Bakame\Http\StructuredFields\StructuredField;

/**
 * @phpstan-import-type SfType from StructuredField
 */
final class ParsedParameters
{
    /**
     * @param array<array-key, array{0:string, 1:SfType}|array{}|SfType|null> $parameters
     */
    public function __construct(
        public readonly array $parameters = [],
        public readonly ViolationList $errors = new ViolationList(),
    ) {
    }
}
