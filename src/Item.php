<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use Stringable;
use function count;
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
use const PHP_ROUND_HALF_EVEN;

final class Item implements StructuredField, ParameterAccess
{
    private function __construct(
        private readonly Token|ByteSequence|DateTimeImmutable|int|float|string|bool $value,
        private readonly Parameters $parameters
    ) {
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
        if ('' === $itemString || 1 === preg_match("/[\r\t\n]|[^\x20-\x7E]/", $itemString)) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for an item contains invalid characters.');
        }

        [$value, $offset] = Parser::parseBareItem($itemString);
        if (!str_contains($itemString, ';') && $offset !== strlen($itemString)) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for an item contains invalid characters.');
        }

        return new self($value, Parameters::fromHttpValue(substr($itemString, $offset)));
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
        ByteSequence|Token|DateTimeInterface|Stringable|string|int|float|bool $value,
        iterable $parameters = []
    ): self {
        return new self(match (true) {
            is_int($value) => self::filterIntegerRange($value, 'Integer'),
            is_float($value) => self::filterDecimal($value),
            is_string($value) || $value instanceof Stringable => self::filterString($value),
            $value instanceof DateTimeInterface => self::filterDate($value),
            default => $value,
        }, Parameters::fromAssociative($parameters));
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
     * Returns the underlying value decoded.
     */
    public function value(): Token|ByteSequence|DateTimeImmutable|string|int|float|bool
    {
        return $this->value;
    }

    public function withValue(Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $value): self
    {
        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (($value instanceof ByteSequence && $this->value instanceof ByteSequence && $value->encoded() === $this->value->encoded())
            || ($value instanceof Token && $this->value instanceof Token && $value->value === $this->value->value)
            || ($value instanceof DateTimeInterface && $this->value instanceof DateTimeInterface && $value == $this->value)
            || $value === $this->value
        ) {
            return $this;
        }

        return self::from($value, $this->parameters);
    }

    public function parameters(): Parameters
    {
        return clone $this->parameters;
    }

    public function prependParameter(string $key, Item|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(string $key, Item|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    public function withoutParameter(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->delete(...$keys));
    }

    public function withParameters(Parameters $parameters): static
    {
        return $this->parameters->toHttpValue() === $parameters->toHttpValue() ? $this : new self($this->value, $parameters);
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

    /**
     * Serialize the Item decimal value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1.5
     */
    private function serializeDecimal(float $value): string
    {
        /** @var string $result */
        $result = json_encode(round($value, 3, PHP_ROUND_HALF_EVEN));

        return str_contains($result, '.') ? $result : $result.'.0';
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
        return $this->value instanceof DateTimeInterface;
    }
}
