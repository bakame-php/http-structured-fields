<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeInterface;
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

    public function equals(mixed $other): bool
    {
        if ($other instanceof ValueAccess) {
            $other = $other->type();
        }

        return $other instanceof self && $other === $this;
    }

    public static function fromValue(ValueAccess|Token|ByteSequence|DateTimeInterface|int|float|string|bool $value): self
    {
        return (new Value($value))->type;
    }

    public static function tryFromValue(mixed $value): self|null
    {
        try {
            return self::fromValue($value); // @phpstan-ignore-line
        } catch (Throwable) {
            return null;
        }
    }
}
