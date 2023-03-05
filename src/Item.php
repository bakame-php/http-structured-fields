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

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
 * @phpstan-import-type DataType from Value
 */
final class Item implements Value
{
    private readonly Type $type;

    private function __construct(
        private readonly Token|ByteSequence|DateTimeImmutable|int|float|string|bool $value,
        private readonly Parameters $parameters
    ) {
        $this->type = Type::fromValue($this->value);
    }

    public function type(): Type
    {
        return $this->type;
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

        return self::from($value, Parameters::fromHttpValue(substr($itemString, $offset)));
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     *
     * @param iterable<string,Value|DataType> $parameters
     */
    public static function fromEncodedByteSequence(Stringable|string $value, iterable $parameters = []): self
    {
        return self::from(ByteSequence::fromEncoded($value), $parameters);
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @param iterable<string,Value|DataType> $parameters
     */
    public static function fromDecodedByteSequence(Stringable|string $value, iterable $parameters = []): self
    {
        return self::from(ByteSequence::fromDecoded($value), $parameters);
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     *
     * @param iterable<string,Value|DataType> $parameters
     */
    public static function fromToken(Stringable|string $value, iterable $parameters = []): self
    {
        return self::from(Token::fromString($value), $parameters);
    }

    /**
     * @param array{
     *     0:DataType,
     *     1?:MemberOrderedMap<string, Value>|iterable<array{0:string, 1:Value|DataType}>
     * } $pair
     */
    public static function fromPair(array $pair): self
    {
        $pair[1] = $pair[1] ?? [];

        return match (true) {
            !array_is_list($pair) => throw new SyntaxError('The pair must be represented by an array as a list.'),  /* @phpstan-ignore-line */
            2 !== count($pair) => throw new SyntaxError('The pair first value should be the item value and the optional second value the item parameters.'), /* @phpstan-ignore-line */
            default => self::from($pair[0], Parameters::fromPairs($pair[1])),
        };
    }

    /**
     * Returns a new instance from a value type and an iterable of key-value parameters.
     *
     * @param iterable<string,Value|DataType> $parameters
     */
    public static function from(mixed $value, iterable $parameters = []): self
    {
        $parameters = Parameters::fromAssociative($parameters);
        if ($value instanceof Value) {
            $parameters = $value->parameters()->mergeAssociative($parameters);
        }

        return new self(match (true) {
            $value instanceof Value => $value->value(),
            $value instanceof DateTimeInterface => self::filterDate($value),
            is_int($value) => self::filterIntegerRange($value, 'Integer'),
            is_float($value) => self::filterDecimal($value),
            is_string($value) || $value instanceof Stringable => self::filterString($value),
            is_bool($value),
            $value instanceof Token,
            $value instanceof ByteSequence => $value,
            default => throw new SyntaxError('The type "'.(is_object($value) ? $value::class : gettype($value)).'" is not supported.')
        }, $parameters);
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

    public function value(): ByteSequence|Token|DateTimeImmutable|string|int|float|bool
    {
        return $this->value;
    }

    public function withValue(mixed $value): static
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        return match (true) {
            ($this->value instanceof ByteSequence || $this->value instanceof Token) && $this->value->equals($value),
            $this->value instanceof DateTimeInterface && $value instanceof DateTimeInterface && $value == $this->value,
            $value instanceof Stringable && $value->__toString() === $this->value,
            $value === $this->value => $this,
            default => self::from($value, $this->parameters),
        };
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function parameter(string $key): mixed
    {
        if ($this->parameters->has($key)) {
            return $this->parameters->get($key)->value();
        }

        return null;
    }

    public function addParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->add($key, $member));
    }

    public function prependParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    public function withoutAllParameters(): static
    {
        return $this->withParameters(Parameters::create());
    }

    public function withoutParameter(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->remove(...$keys));
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
            is_int($this->value) => (string) $this->value,
            is_float($this->value) => self::serializeDecimal($this->value),
            is_bool($this->value) => '?'.($this->value ? '1' : '0'),
            $this->value instanceof Token => $this->value->value,
            $this->value instanceof DateTimeImmutable => '@'.$this->value->getTimestamp(),
            default => ':'.$this->value->encoded().':',
        }.$this->parameters->toHttpValue();
    }

    public function __toString(): string
    {
        return $this->toHttpValue();
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
}
