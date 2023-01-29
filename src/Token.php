<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use function preg_match;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.4
 */
final class Token
{
    private function __construct(public readonly string $value)
    {
        if (1 !== preg_match("/^([a-z*][a-z\d:\/!#\$%&'*+\-.^_`|~]*)$/i", $this->value)) {
            throw new SyntaxError('Invalid characters in token.');
        }
    }

    public static function fromString(Stringable|string $value): self
    {
        return new self((string) $value);
    }
}
