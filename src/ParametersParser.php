<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;

/**
 * @phpstan-import-type SfType from StructuredField
 */
interface ParametersParser
{
    /**
     * Returns an array representation from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     *
     * @throws SyntaxError If the string is not a valid
     *
     * @return array<string, SfType>
     */
    public function parseParameters(Stringable|string $httpValue): array;
}
