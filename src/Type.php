<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
 */
enum Type: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    case String = 'string';
    case Token = 'token';
    case ByteSequence = 'binary';
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
     * @throws InvalidArgument if the value can not be resolved into a supported HTTP structured field data type
     */
    public static function fromVariable(mixed $value): self
    {
        return self::tryFromVariable($value) ?? throw new InvalidArgument((is_object($value) ? 'An instance of "'.$value::class.'"' : 'A value of type "'.gettype($value).'"').' can not be used as an HTTP structured field data type.');
    }

    public static function tryFromVariable(mixed $variable): self|null
    {
        return match (true) {
            $variable instanceof Item,
            $variable instanceof Token,
            $variable instanceof DisplayString,
            $variable instanceof ByteSequence => $variable->type(),
            $variable instanceof DateTimeInterface => Type::Date,
            is_int($variable) => Type::Integer,
            is_float($variable) => Type::Decimal,
            is_bool($variable) => Type::Boolean,
            is_string($variable) && 1 !== preg_match('/[^\x20-\x7f]/', $variable) => Type::String,
            default => null,
        };
    }

    public function supports(mixed $value): bool
    {
        $new = self::tryFromVariable($value);

        return null !== $new && $new->equals($this);
    }
}
