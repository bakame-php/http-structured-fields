<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use function preg_match;

final class Token
{
    private function __construct(private string $value)
    {
        if (1 !== preg_match("/^([a-z*][a-z\d:\/!#\$%&'*+\-.^_`|~]*)$/i", $this->value)) {
            throw new SyntaxError('Invalid characters in token');
        }
    }

    public static function fromString(string|Stringable $value): self
    {
        return new self((string) $value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
