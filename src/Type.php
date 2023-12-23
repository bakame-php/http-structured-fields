<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
 */
enum Type: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case String = 'string';
    case Token = 'token';
    case ByteSequence = 'bytesequence';
    case DisplayString = 'displaystring';
    case Boolean = 'boolean';
    case Date = 'date';

    public function equals(mixed $other): bool
    {
        return match (true) {
            $other instanceof ValueAccess => $other->type() === $this,
            default => $other instanceof self && $other === $this,
        };
    }

    /**
     * @throws InvalidArgument if the value can not be resolved into a supported HTTP structured field data type
     */
    public static function fromValue(mixed $value): self
    {
        return self::tryFromValue($value) ?? throw new InvalidArgument((is_object($value) ? 'An instance of "'.$value::class.'"' : 'A value of type "'.gettype($value).'"').' can not be used as an HTTP structured field data type.');
    }

    public static function tryFromValue(mixed $value): self|null
    {
        return match (true) {
            $value instanceof ValueAccess,
            $value instanceof Token,
            $value instanceof DisplayString,
            $value instanceof ByteSequence => $value->type(),
            $value instanceof DateTimeInterface => Type::Date,
            is_int($value) => Type::Integer,
            is_float($value) => Type::Decimal,
            is_bool($value) => Type::Boolean,
            is_string($value) => match (true) {
                null !== Token::tryFromString($value) => Type::Token,
                null !== ByteSequence::tryFromEncoded($value) => Type::ByteSequence,
                1 === preg_match('/[^\x20-\x7f]/', $value) => Type::DisplayString,
                default => Type::String,
            },
            default => null,
        };
    }
}
