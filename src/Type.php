<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use Stringable;
use Throwable;
use function abs;
use function floor;
use function gettype;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_replace;
use function round;
use function str_contains;
use const PHP_ROUND_HALF_EVEN;

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
        if ($other instanceof Value) {
            $other = $other->type();
        }

        return $other instanceof self && $other === $this;
    }

    public static function fromValue(mixed $value): self
    {
        return match (true) {
            $value instanceof Value,
            $value instanceof Token,
            $value instanceof ByteSequence => $value->type(),
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

    public static function serialize(mixed $value): string
    {
        return match (true) {
            is_string($value) => '"'.preg_replace('/(["\\\])/', '\\\$1', $value).'"',
            is_int($value) => (string) $value,
            is_float($value) => self::serializeDecimal($value),
            is_bool($value) => '?'.($value ? '1' : '0'),
            $value instanceof Token => $value->value,
            $value instanceof DateTimeImmutable => '@'.$value->getTimestamp(),
            $value instanceof ByteSequence => ':'.$value->encoded().':',
            default => throw new SyntaxError('The type "'.(is_object($value) ? $value::class : gettype($value)).'" is not supported.')
        };
    }

    /**
     * Serialize the Item decimal value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1.5
     */
    private static function serializeDecimal(float $value): string
    {
        /** @var string $result */
        $result = json_encode(round($value, 3, PHP_ROUND_HALF_EVEN));

        return str_contains($result, '.') ? $result : $result.'.0';
    }

    /**
     * @return array{value:Token|ByteSequence|DateTimeImmutable|int|float|string|bool, type:Type}
     */
    public static function convert(mixed $value): array
    {
        return match (true) {
            $value instanceof Value => ['value' => $value->value(), 'type' => $value->type()],
            $value instanceof DateTimeInterface => ['value' => self::filterDate($value), 'type' => self::Date],
            is_int($value) => ['value' => self::filterIntegerRange($value, 'Integer'), 'type' => self::Integer],
            is_float($value) => ['value' => self::filterDecimal($value), 'type' => self::Decimal],
            is_string($value) || $value instanceof Stringable => ['value' => self::filterString($value), 'type' => self::String],
            is_bool($value) => ['value' => $value, 'type' => self::Boolean],
            $value instanceof Token,
            $value instanceof ByteSequence => ['value' => $value, 'type' => $value->type()],
            default => throw new SyntaxError('The type "'.(is_object($value) ? $value::class : gettype($value)).'" is not supported.')
        };
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.1
     */
    private static function filterIntegerRange(int $value, string $type): int
    {
        if (abs($value) > 999_999_999_999_999) {
            throw new SyntaxError($type.' are limited to 15 digits.');
        }

        return $value;
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.2
     */
    private static function filterDecimal(float $value): float
    {
        if (abs(floor($value)) > 999_999_999_999) {
            throw new SyntaxError('Integer portion of decimals is limited to 12 digits.');
        }

        return $value;
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.3
     */
    private static function filterString(Stringable|string $value): string
    {
        $value = (string) $value;
        if (1 === preg_match('/[^\x20-\x7E]/i', $value)) {
            throw new SyntaxError('The string contains invalid characters.');
        }

        return $value;
    }

    /**
     * Filter a date according to draft-ietf-httpbis-sfbis-latest.
     *
     * @see https://httpwg.org/http-extensions/draft-ietf-httpbis-sfbis.html#section-3.3.7
     */
    private static function filterDate(DateTimeInterface $value): DateTimeImmutable
    {
        self::filterIntegerRange($value->getTimestamp(), 'Date timestamp');

        return $value instanceof DateTimeImmutable ? $value : DateTimeImmutable::createFromInterface($value);
    }
}
