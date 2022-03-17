<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function preg_match;

final class Token implements StructuredField
{
    public function __construct(private string $value)
    {
        if (1 !== preg_match("/^([a-z*][a-z0-9:\/\!\#\$%&'\*\+\-\.\^_`\|~]*)$/i", $this->value)) {
            throw new SyntaxError('Invalid characters in token');
        }
    }

    /**
     * @param array{value:string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['value']);
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
