<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-import-type SfType from StructuredField
 */
interface ListParser
{
    /**
     * Returns an ordered list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.1
     *
     * @return array<array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}>
     */
    public function parseList(Stringable|string $httpValue): array;
}
