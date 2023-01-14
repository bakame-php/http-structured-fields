<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_replace;
use function round;
use function str_contains;
use function strlen;
use function substr;
use function trim;

final class Item implements StructuredField, ParameterAccess
{
    private function __construct(
        private readonly Token|ByteSequence|DateTimeImmutable|int|float|string|bool $value,
        private readonly Parameters $parameters
    ) {
    }

    /**
     * @param array{
     *     0:DataType,
     *     1?:MemberOrderedMap<string, Item>|iterable<array{0:string, 1:Item|DataType}>
     * } $pair
     */
    public static function fromPair(array $pair): self
    {
        if (!array_is_list($pair)) { /* @phpstan-ignore-line */
            throw new SyntaxError('The pair must be represented by an array as a list.');
        }

        $pair[1] = $pair[1] ?? [];
        if (2 !== count($pair)) { /* @phpstan-ignore-line */
            throw new SyntaxError('The pair first value should be the item value and the optional second value the item parameters.');
        }

        return self::from($pair[0], Parameters::fromPairs($pair[1]));
    }

    /**
     * Returns a new instance from a value type and an iterable of key-value parameters.
     *
     * @param iterable<string,Item|DataType> $parameters
     */
    public static function from(
        Token|ByteSequence|Stringable|DateTimeInterface|int|float|string|bool $value,
        iterable $parameters = []
    ): self {
        return new self(self::filterValue($value), Parameters::fromAssociative($parameters));
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     *
     * @param iterable<string,Item|DataType> $parameters
     */
    public static function fromEncodedByteSequence(Stringable|string $value, iterable $parameters = []): self
    {
        return self::from(ByteSequence::fromEncoded($value), $parameters);
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @param iterable<string,Item|DataType> $parameters
     */
    public static function fromDecodedByteSequence(Stringable|string $value, iterable $parameters = []): self
    {
        return self::from(ByteSequence::fromDecoded($value), $parameters);
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     *
     * @param iterable<string,Item|DataType> $parameters
     */
    public static function fromToken(Stringable|string $value, iterable $parameters = []): self
    {
        return self::from(Token::fromString($value), $parameters);
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
     * Filter a decimal according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.1
     */
    private static function filterInteger(int $value, string $type): int
    {
        if ($value > 999_999_999_999_999 || $value < -999_999_999_999_999) {
            throw new SyntaxError($type.' are limited to 15 digits.');
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
        self::filterInteger($value->getTimestamp(), 'Date timestamp');

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    /**
     * Returns a new instance from an HTTP Header or Trailer value string
     * in compliance with RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
     */
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        $itemString = trim((string) $httpValue, ' ');

        [$value, $parameters] = match (true) {
            1 === preg_match("/[\r\t\n]|[^\x20-\x7E]/", $itemString),
            '' === $itemString => throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for an item contains invalid characters.'),
            '"' === $itemString[0] => self::parseString($itemString),
            ':' === $itemString[0] => self::parseBytesSequence($itemString),
            '?' === $itemString[0] => self::parseBoolean($itemString),
            '@' === $itemString[0] => self::parseDate($itemString),
            1 === preg_match('/^(-?\d)/', $itemString) => self::parseNumber($itemString),
            1 === preg_match('/^([a-z*])/i', $itemString) => self::parseToken($itemString),
            default => throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for an item is unknown or unsupported.'),
        };

        return new self($value, Parameters::fromHttpValue($parameters));
    }

    /**
     * Parses an HTTP textual representation of an Item as a Token Data Type.
     *
     * @return array{0:Token, 1:string}
     */
    private static function parseToken(string $string): array
    {
        $regexp = "^(?<token>[a-z*][a-z0-9:\/\!\#\$%&'\*\+\-\.\^_`\|~]*)";
        if (!str_contains($string, ';')) {
            $regexp .= '$';
        }

        if (1 !== preg_match('/'.$regexp.'/i', $string, $found)) {
            throw new SyntaxError("The HTTP textual representation \"$string\" for a Token contains invalid characters.");
        }

        return [
            Token::fromString($found['token']),
            substr($string, strlen($found['token'])),
        ];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Boolean Data Type.
     *
     * @return array{0:bool, 1:string}
     */
    private static function parseBoolean(string $string): array
    {
        if (1 !== preg_match('/^\?[01]/', $string)) {
            throw new SyntaxError("The HTTP textual representation \"$string\" for a Boolean contains invalid characters.");
        }

        return [$string[1] === '1', substr($string, 2)];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Byte Sequence Type.
     *
     * @return array{0:ByteSequence, 1:string}
     */
    private static function parseBytesSequence(string $string): array
    {
        if (1 !== preg_match('/^:(?<bytes>[a-z\d+\/=]*):/i', $string, $matches)) {
            throw new SyntaxError("The HTTP textual representation \"$string\" for a Byte sequence contains invalid characters.");
        }

        return [ByteSequence::fromEncoded($matches['bytes']), substr($string, strlen($matches[0]))];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Data Type number.
     *
     * @return array{0:int|float, 1:string}
     */
    private static function parseNumber(string $string): array
    {
        $regexp = '^(?<number>-?\d+(?:\.\d+)?)(?:[^\d.]|$)';
        if (!str_contains($string, ';')) {
            $regexp = '^(?<number>-?\d+(?:\.\d+)?)$';
        }

        if (1 !== preg_match('/'.$regexp.'/', $string, $found)) {
            throw new SyntaxError("The HTTP textual representation \"$string\" for a Number contains invalid characters.");
        }

        $number = match (true) {
            1 === preg_match('/^-?\d{1,12}\.\d{1,3}$/', $found['number']) => (float) $found['number'],
            1 === preg_match('/^-?\d{1,15}$/', $found['number']) => (int) $found['number'],
            default => throw new SyntaxError("The HTTP textual representation \"$string\" for a Number contain too many digits."),
        };

        return [$number, substr($string, strlen($found['number']))];
    }

    /**
     * Parses an HTTP textual representation of an Item as a Data Type number.
     *
     * @return array{0:DateTimeImmutable, 1:string}
     */
    private static function parseDate(string $string): array
    {
        [$timestamp, $parameters] = self::parseNumber(substr($string, 1));
        if (!is_int($timestamp)) {
            throw new SyntaxError("The HTTP textual representation \"$string\" for a date contains invalid characters.");
        }

        return [
            (new DateTimeImmutable('NOW', new DateTimeZone('UTC')))->setTimestamp($timestamp),
            $parameters,
        ];
    }

    /**
     * Parses an HTTP textual representation of an Item as a String Data Type.
     *
     * @return array{0:string, 1:string}
     */
    private static function parseString(string $string): array
    {
        $originalString = $string;
        $string = substr($string, 1);
        $returnValue = '';

        while ('' !== $string) {
            $char = $string[0];
            $string = substr($string, 1);

            if ($char === '"') {
                return [$returnValue, $string];
            }

            if ($char !== '\\') {
                $returnValue .= $char;
                continue;
            }

            if ($string === '') {
                throw new SyntaxError("The HTTP textual representation \"$originalString\" for a String contains an invalid end string.");
            }

            $char = $string[0];
            $string = substr($string, 1);
            if (!in_array($char, ['"', '\\'], true)) {
                throw new SyntaxError("The HTTP textual representation \"$originalString\" for a String contains invalid characters.");
            }

            $returnValue .= $char;
        }

        throw new SyntaxError("The HTTP textual representation \"$originalString\" for a String contains an invalid end string.");
    }

    /**
     * Serialize the Item decimal value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1.5
     */
    private function serializeDecimal(float $value): string
    {
        /** @var string $result */
        $result = json_encode(round($value, 3, PHP_ROUND_HALF_EVEN));
        if (str_contains($result, '.')) {
            return $result;
        }

        return $result.'.0';
    }

    /**
     * Returns the underlying value decoded.
     */
    public function value(): Token|ByteSequence|DateTimeImmutable|int|float|string|bool
    {
        return $this->value;
    }

    public function withValue(Token|ByteSequence|DateTimeInterface|Stringable|int|float|string|bool $value): self
    {
        $newValue = self::filterValue($value);

        if (
            ($newValue === $this->value)
            || ($newValue instanceof DateTimeImmutable && $this->value instanceof DateTimeImmutable && $this->value == $newValue)
            || ($newValue instanceof Token && $this->value instanceof Token && $this->value->value === $newValue->value)
            || ($newValue instanceof ByteSequence && $this->value instanceof ByteSequence && $this->value->encoded() === $newValue->encoded())
        ) {
            return $this;
        }

        return self::from($newValue, $this->parameters);
    }

    public function parameters(): Parameters
    {
        return clone $this->parameters;
    }

    public function prependParameter(string $key, Item|ByteSequence|Token|DateTimeInterface|Stringable|bool|int|float|string $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(string $key, Item|ByteSequence|Token|DateTimeInterface|Stringable|bool|int|float|string $member): static
    {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    public function withoutParameter(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->delete(...$keys));
    }

    public function withParameters(Parameters $parameters): static
    {
        if ($this->parameters->toHttpValue() === $parameters->toHttpValue()) {
            return $this;
        }

        return new self($this->value, $parameters);
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1
     */
    public function toHttpValue(): string
    {
        return match (true) {
            is_string($this->value) => '"'.preg_replace('/(["\\\])/', '\\\$1', $this->value).'"',
            is_int($this->value) => (string)$this->value,
            is_float($this->value) => $this->serializeDecimal($this->value),
            is_bool($this->value) => '?'.($this->value ? '1' : '0'),
            $this->value instanceof Token => $this->value->value,
            $this->value instanceof DateTimeImmutable => '@'.$this->value->getTimestamp(),
            default => ':'.$this->value->encoded().':',
        }.$this->parameters->toHttpValue();
    }

    public function isInteger(): bool
    {
        return is_int($this->value);
    }

    public function isDecimal(): bool
    {
        return is_float($this->value);
    }

    public function isBoolean(): bool
    {
        return is_bool($this->value);
    }

    public function isString(): bool
    {
        return is_string($this->value);
    }

    public function isToken(): bool
    {
        return $this->value instanceof Token;
    }

    public function isByteSequence(): bool
    {
        return $this->value instanceof ByteSequence;
    }

    public function isDate(): bool
    {
        return $this->value instanceof DateTimeImmutable;
    }

    private static function filterValue(float|bool|int|string|Token|ByteSequence|DateTimeInterface|Stringable $value): ByteSequence|DateTimeImmutable|Token|string|int|bool|float
    {
        return match (true) {
            is_int($value) => self::filterInteger($value, 'Integer'),
            is_float($value) => self::filterDecimal($value),
            is_string($value) || $value instanceof Stringable => self::filterString($value),
            $value instanceof DateTimeInterface => self::filterDate($value),
            default => $value,
        };
    }
}
