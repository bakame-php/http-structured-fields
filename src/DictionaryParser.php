<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-import-type SfType from StructuredField
 */
interface DictionaryParser
{
    /**
     * Returns an ordered map represented as a PHP associative array from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.2.2
     *
     * @return array<string, array{0:SfType|array<array{0:SfType, 1:array<string, SfType>}>, 1:array<string, SfType>}>
     */
    public function parseDictionary(Stringable|string $httpValue): array;
}
