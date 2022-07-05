<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use function preg_match;

final class Token implements StructuredField
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

    public function toHttpValue(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->value;
    }
}
