<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use Stringable;

interface ValueParser
{
    /**
     * Returns the data type value represented as a PHP type from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#name-parsing-an-item
     *
     * @throws SyntaxError
     */
    public function parseValue(Stringable|string $httpValue): ByteSequence|Token|DateTimeImmutable|string|int|float|bool;
}
