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
    public static function fromVariable(mixed $value): self
    {
        return self::tryFromVariable($value) ?? throw new InvalidArgument((is_object($value) ? 'An instance of "'.$value::class.'"' : 'A value of type "'.gettype($value).'"').' can not be used as an HTTP structured field data type.');
    }

    public static function tryFromVariable(mixed $variable): self|null
    {
        return match (true) {
            $variable instanceof ValueAccess,
            $variable instanceof Token,
            $variable instanceof DisplayString,
            $variable instanceof ByteSequence => $variable->type(),
            $variable instanceof DateTimeInterface => Type::Date,
            is_int($variable) => Type::Integer,
            is_float($variable) => Type::Decimal,
            is_bool($variable) => Type::Boolean,
            is_string($variable) => match (true) {
                null !== Token::tryFromString($variable) => Type::Token,
                null !== ByteSequence::tryFromEncoded($variable) => Type::ByteSequence,
                1 === preg_match('/[^\x20-\x7f]/', $variable) => Type::DisplayString,
                default => Type::String,
            },
            default => null,
        };
    }

    /**
     * @deprecated since version 1.2.0 will be removed in the next major release.
     * @see Type::fromVariable()
     * @codeCoverageIgnore
     *
     * @throws InvalidArgument if the value can not be resolved into a supported HTTP structured field data type
     */
    public static function fromValue(mixed $value): self
    {
        return self::fromVariable($value);
    }

    /**
     * @deprecated since version 1.2.0 will be removed in the next major release.
     * @see Type::tryFromVariable()
     * @codeCoverageIgnore
     */
    public static function tryFromValue(mixed $value): self|null
    {
        return self::tryFromVariable($value);
    }
}
