<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;
use Stringable;
use Throwable;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
 */
enum Type
{
    case Integer;
    case Decimal;
    case String;
    case Token;
    case ByteSequence;
    case Boolean;
    case Date;

    public static function fromValue(mixed $value): self
    {
        if ($value instanceof Value) {
            return $value->type();
        }

        return match (true) {
            $value instanceof Token => self::Token,
            $value instanceof ByteSequence => self::ByteSequence,
            $value instanceof DateTimeInterface => self::Date,
            $value instanceof Stringable, is_string($value) => self::String,
            is_bool($value) => self::Boolean,
            is_int($value) => self::Integer,
            is_float($value) => self::Decimal,
            default => throw new SyntaxError('The type "'.(is_object($value) ? $value::class : gettype($value)).'" is not supported.'),
        };
    }

    public static function tryFromValue(mixed $value): self|null
    {
        try {
            return self::fromValue($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof Value) {
            $other = $other->type();
        }

        return $other instanceof self && $other === $this;
    }
}
