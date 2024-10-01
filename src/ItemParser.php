<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-import-type SfType from StructuredField
 */
interface ItemParser
{
    /**
     * Returns an Item represented as a PHP list array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#name-parsing-an-item
     *
     * @return array{0:SfType, 1:array<string, SfType>}
     */
    public function parseItem(Stringable|string $httpValue): array;
}
