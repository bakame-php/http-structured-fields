<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Stringable;
use Throwable;
use ValueError;

use function abs;
use function date_default_timezone_get;
use function floor;
use function is_float;
use function is_int;
use function json_encode;
use function preg_match;
use function preg_replace;
use function round;

use const JSON_PRESERVE_ZERO_FRACTION;
use const PHP_ROUND_HALF_EVEN;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
 * @internal
 */
final class Value
{
    public readonly Token|ByteSequence|DisplayString|DateTimeImmutable|int|float|string|bool $value;
    public readonly Type $type;

    /**
     * @throws ValueError
     */
    public function __construct(mixed $value)
    {
        $this->value = match (true) {
            $value instanceof Item => $value->value(),
            $value instanceof Token,
            $value instanceof ByteSequence,
            $value instanceof DisplayString,
            false === $value,
            $value => $value,
            $value instanceof DateTimeInterface => self::filterDate($value),
            is_int($value) => self::filterIntegerRange($value, 'Integer'),
            is_float($value) => self::filterDecimal($value),
            is_string($value) => self::filterString($value),
            default => throw new ValueError('Unknown or unsupported type.')
        };
        $this->type = Type::fromVariable($this->value);
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3.1
     */
    private static function filterIntegerRange(int $value, string $type): int
    {
        return match (true) {
            999_999_999_999_999 < abs($value) => throw new SyntaxError($type.' are limited to 15 digits.'),
            default => $value,
        };
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3.2
     */
    private static function filterDecimal(float $value): float
    {
        return match (true) {
            999_999_999_999 < abs(floor($value)) => throw new SyntaxError('Integer portion of decimals is limited to 12 digits.'),
            default => $value,
        };
    }

    /**
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3.3
     */
    private static function filterString(string $value): string
    {
        return match (true) {
            1 === preg_match('/[^\x20-\x7E]/i', $value) => throw new SyntaxError('The string contains invalid characters.'),
            default => $value,
        };
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

    public static function fromEncodedDisplayString(Stringable|string $value): self
    {
        return new self(DisplayString::fromEncoded($value));
    }

    public static function fromDecodedDisplayString(Stringable|string $value): self
    {
        return new self(DisplayString::fromDecoded($value));
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
        try {
            $value = DateTimeImmutable::createFromFormat($format, $datetime);
        } catch (Exception $exception) {
            throw new SyntaxError('The date notation `'.$datetime.'` is incompatible with the date format `'.$format.'`.', 0, $exception);
        }

        return match (false) {
            $value => throw new SyntaxError('The date notation `'.$datetime.'` is incompatible with the date format `'.$format.'`.'),
            default => new self($value),
        };
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
        return new self((string) $value);
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
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.1
     */
    public function serialize(Ietf $rfc = Ietf::Rfc9651): string
    {
        $rfc->supports($this->type) || throw new SyntaxError('The '.$this->type->value.' type is not serializable by '.$rfc->value);

        return match (true) {
            $this->value instanceof DateTimeImmutable => '@'.$this->value->getTimestamp(),
            $this->value instanceof Token => $this->value->toString(),
            $this->value instanceof ByteSequence => ':'.$this->value->encoded().':',
            $this->value instanceof DisplayString => '%"'.$this->value->encoded().'"',
            is_int($this->value) => (string) $this->value,
            is_float($this->value) => (string) json_encode(round($this->value, 3, PHP_ROUND_HALF_EVEN), JSON_PRESERVE_ZERO_FRACTION),
            $this->value,
            false === $this->value => '?'.($this->value ? '1' : '0'),
            default => '"'.preg_replace('/(["\\\])/', '\\\$1', $this->value).'"',
        };
    }

    public function equals(mixed $value): bool
    {
        if ($value instanceof self) {
            $value = $value->value;
        }

        return match (true) {
            ($this->value instanceof ByteSequence || $this->value instanceof Token || $this->value instanceof DisplayString) && $this->value->equals($value),
            $this->value instanceof DateTimeInterface && $value instanceof DateTimeInterface && $value == $this->value,
            $value === $this->value => true,
            default => false,
        };
    }
}
