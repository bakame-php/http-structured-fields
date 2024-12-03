<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use Throwable;

use function preg_match;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#name-tokens
 */
final class Token
{
    private function __construct(private readonly string $value)
    {
        if (1 !== preg_match("/^([a-z*][a-z\d:\/!#\$%&'*+\-.^_`|~]*)$/i", $this->value)) {
            throw new SyntaxError('The token '.$this->value.' contains invalid characters.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }

    public static function tryFromString(Stringable|string $value): ?self
    {
        try {
            return self::fromString($value);
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromString(Stringable|string $value): self
    {
        return new self((string)$value);
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    public function type(): Type
    {
        return Type::Token;
    }
}
