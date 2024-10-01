<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-import-type SfType from StructuredField
 */
interface InnerListParser
{
    /**
     * Returns an inner list represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.1.2
     *
     * @return array{0:array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}
     */
    public function parseInnerList(Stringable|string $httpValue): array;
}
