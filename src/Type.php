<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
 */
enum Type: string
{
    public const MAXIMUM_INT = 999_999_999_999_999;
    public const MAXIMUM_FLOAT = 999_999_999_999;

    case Integer = 'integer';
    case Decimal = 'decimal';
    case String = 'string';
    case Token = 'token';
    case Bytes = 'binary';
    case DisplayString = 'displaystring';
    case Boolean = 'boolean';
    case Date = 'date';

    public function equals(mixed $other): bool
    {
        return match (true) {
            $other instanceof Item => $other->type() === $this,
            default => $other instanceof self && $other === $this,
        };
    }

    public function isOneOf(mixed ...$other): bool
    {
        foreach ($other as $item) {
            if ($this->equals($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws SyntaxError if the value can not be resolved into a supported HTTP structured field data type
     */
    public static function fromVariable(mixed $value): self
    {
        return self::tryFromVariable($value) ?? throw new SyntaxError(match (true) {
            $value instanceof DateTimeInterface => 'The integer representation of a date is limited to 15 digits for a HTTP structured field date type.',
            is_int($value) => 'The integer is limited to 15 digits for a HTTP structured field integer type.',
            is_float($value) => 'The integer portion of decimals is limited to 12 digits for a HTTP structured field decimal type.',
            is_string($value) => 'The string contains characters that are invalid for a HTTP structured field string type',
            default => (is_object($value) ? 'An instance of "'.$value::class.'"' : 'A value of type "'.gettype($value).'"').' can not be used as an HTTP structured field value type.',
        });
    }

    public static function tryFromVariable(mixed $variable): ?self
    {
        return match (true) {
            $variable instanceof Item,
            $variable instanceof Token,
            $variable instanceof DisplayString,
            $variable instanceof Bytes => $variable->type(),
            $variable instanceof DateTimeInterface && self::MAXIMUM_INT >= abs($variable->getTimestamp()) => Type::Date,
            is_int($variable) && self::MAXIMUM_INT >= abs($variable) => Type::Integer,
            is_float($variable) && self::MAXIMUM_FLOAT >= abs(floor($variable)) => Type::Decimal,
            is_bool($variable) => Type::Boolean,
            is_string($variable) && 1 !== preg_match('/[^\x20-\x7f]/', $variable) => Type::String,
            default => null,
        };
    }

    public function supports(mixed $value): bool
    {
        return self::tryFromVariable($value)?->equals($this) ?? false;
    }
}
