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
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.4
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.5
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.6
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.7
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.2.8
     * @see https://www.ietf.org/archive/id/draft-ietf-httpbis-sfbis-02.html#section-4.2.9
     *
     * @throws SyntaxError
     */
    public function parseValue(Stringable|string $httpValue): ByteSequence|Token|DateTimeImmutable|string|int|float|bool;
}
