<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use Throwable;
use const PHP_ROUND_HALF_EVEN;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
 * @internal
 */
final class Value
{
    public readonly Token|ByteSequence|DateTimeImmutable|int|float|string|bool $value;
    public readonly Type $type;

    public function __construct(mixed $value)
    {
        [$this->value, $this->type] = match (true) {
            $value instanceof ValueAccess => [$value->value(), $value->type()],
            $value instanceof DateTimeInterface => [self::filterDate($value), Type::Date],
            is_int($value) => [self::filterIntegerRange($value, 'Integer'), Type::Integer],
            is_float($value) => [self::filterDecimal($value), Type::Decimal],
            is_string($value) || $value instanceof Stringable => [self::filterString($value), Type::String],
            is_bool($value) => [$value, Type::Boolean],
            $value instanceof Token,
            $value instanceof ByteSequence => [$value, $value->type()],
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

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     */
    public static function fromEncodedByteSequence(Stringable|string $value): self
    {
        return new self(ByteSequence::fromEncoded($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     */
    public static function fromDecodedByteSequence(Stringable|string $value): self
    {
        return new self(ByteSequence::fromDecoded($value));
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     */
    public static function fromToken(Stringable|string $value): self
    {
        return new self(Token::fromString($value));
    }

    /**
     * Returns a new instance from a timestamp and an iterable of key-value parameters.
     */
    public static function fromTimestamp(int $timestamp): self
    {
        return new self((new DateTimeImmutable())->setTimestamp($timestamp));
    }

    /**
     * Returns a new instance from a date format its date string representation and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDateFormat(string $format, string $datetime): self
    {
        $value = DateTimeImmutable::createFromFormat($format, $datetime);
        if (false === $value) {
            throw new SyntaxError('The date notation `'.$datetime.'` is incompatible with the date format `'.$format.'`.');
        }

        return new self($value);
    }

    /**
     * Returns a new instance from a string parsable by DateTimeImmutable constructor, an optional timezone and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDateString(string $datetime, DateTimeZone|string|null $timezone = null): self
    {
        $timezone ??= date_default_timezone_get();
        if (!$timezone instanceof DateTimeZone) {
            try {
                $timezone = new DateTimeZone($timezone);
            } catch (Throwable $exception) {
                throw new SyntaxError('The timezone could not be instantiated.', 0, $exception);
            }
        }

        try {
            $value = new DateTimeImmutable($datetime, $timezone);
        } catch (Throwable $exception) {
            throw new SyntaxError('Unable to create a '.DateTimeImmutable::class.' instance with the date notation `'.$datetime.'.`', 0, $exception);
        }

        return new self($value);
    }

    /**
     * Returns a new instance from a DatetimeInterface implementing object.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDate(DateTimeInterface $datetime): self
    {
        return new self($datetime);
    }

    public static function fromDecimal(int|float $value): self
    {
        return new self((float) $value);
    }

    public static function fromInteger(int|float $value): self
    {
        return new self((int) $value);
    }

    public static function fromString(Stringable|string $value): self
    {
        return new self($value);
    }

    public static function true(): self
    {
        return new self(true);
    }

    public static function false(): self
    {
        return new self(false);
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1
     */
    public function serialize(): string
    {
        return match (true) {
            is_string($this->value) => '"'.preg_replace('/(["\\\])/', '\\\$1', $this->value).'"',
            is_int($this->value) => (string) $this->value,
            is_float($this->value) => self::serializeDecimal($this->value),
            is_bool($this->value) => '?'.($this->value ? '1' : '0'),
            $this->value instanceof Token => $this->value->toString(),
            $this->value instanceof DateTimeImmutable => '@'.$this->value->getTimestamp(),
            default => ':'.$this->value->encoded().':',
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

    public function equals(mixed $value): bool
    {
        if ($value instanceof self) {
            $value = $value->value;
        }

        return match (true) {
            ($this->value instanceof ByteSequence || $this->value instanceof Token) && $this->value->equals($value),
            $this->value instanceof DateTimeInterface && $value instanceof DateTimeInterface && $value == $this->value,
            $value instanceof Stringable && $value->__toString() === $this->value,
            $value === $this->value => true,
            default => false,
        };
    }
}
