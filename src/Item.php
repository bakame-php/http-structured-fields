<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use function count;
use function preg_match;
use function str_contains;
use function strlen;
use function substr;
use function trim;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
 *
 * @phpstan-import-type SfItem from StructuredField
 * @phpstan-import-type SfItemInput from StructuredField
 */
final class Item implements ParameterAccess, ValueAccess
{
    private function __construct(
        private readonly Value $value,
        private readonly Parameters $parameters
    ) {
    }

    /**
     * Returns a new instance from a value type and an iterable of key-value parameters.
     *
     * @param iterable<string, SfItemInput> $parameters
     */
    public static function from(mixed $value, iterable $parameters = []): self
    {
        if (!$parameters instanceof Parameters) {
            $parameters = Parameters::fromAssociative($parameters);
        }

        return new self(new Value($value), $parameters);
    }

    /**
     * @param array{
     *     0:SfItemInput,
     *     1?:MemberOrderedMap<string, SfItem>|iterable<array{0:string, 1:SfItemInput}>
     * } $pair
     */
    public static function fromPair(array $pair): self
    {
        $pair[1] = $pair[1] ?? [];

        if (!array_is_list($pair)) { /* @phpstan-ignore-line */
            throw new SyntaxError('The pair must be represented by an array as a list.');
        }

        if (2 !== count($pair)) { /* @phpstan-ignore-line */
            throw new SyntaxError('The pair first value should be the item value and the optional second value the item parameters.');
        }

        if (!$pair[1] instanceof Parameters) {
            $pair[1] = Parameters::fromPairs($pair[1]);
        }

        return new self(new Value($pair[0]), $pair[1]);
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

        return new self(new Value($value), Parameters::fromHttpValue(substr($itemString, $offset)));
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     */
    public static function fromEncodedByteSequence(Stringable|string $value): self
    {
        return self::fromValue(Value::fromEncodedByteSequence($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     */
    public static function fromDecodedByteSequence(Stringable|string $value): self
    {
        return self::fromValue(Value::fromDecodedByteSequence($value));
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     */
    public static function fromToken(Stringable|string $value): self
    {
        return self::fromValue(Value::fromToken($value));
    }

    /**
     * Returns a new instance from a timestamp and an iterable of key-value parameters.
     */
    public static function fromTimestamp(int $timestamp): self
    {
        return self::fromValue(Value::fromTimestamp($timestamp));
    }

    /**
     * Returns a new instance from a date format its date string representation and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDateFormat(string $format, string $datetime): self
    {
        return self::fromValue(Value::fromDateFormat($format, $datetime));
    }

    /**
     * Returns a new instance from a string parsable by DateTimeImmutable constructor, an optional timezone and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDateString(string $datetime, DateTimeZone|string|null $timezone = null): self
    {
        return self::fromValue(Value::fromDateString($datetime, $timezone));
    }

    /**
     * Returns a new bare instance from value.
     */
    private static function fromValue(Value $value): self
    {
        return new self($value, Parameters::create());
    }

    public static function fromDecimal(int|float $value): self
    {
        return self::fromValue(Value::fromDecimal($value));
    }

    public static function fromInteger(int|float $value): self
    {
        return self::fromValue(Value::fromInteger($value));
    }

    public static function true(): self
    {
        return self::fromValue(Value::true());
    }

    public static function false(): self
    {
        return self::fromValue(Value::false());
    }

    public function value(): ByteSequence|Token|DateTimeImmutable|string|int|float|bool
    {
        return $this->value->value;
    }

    public function type(): Type
    {
        return $this->value->type;
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function parameter(MapKey|string $key): mixed
    {
        try {
            return $this->parameters->get($key instanceof MapKey ? $key->value : $key)->value();
        } catch (StructuredFieldError) {
            return null;
        }
    }

    public function __toString(): string
    {
        return $this->toHttpValue();
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-4.1
     */
    public function toHttpValue(): string
    {
        return $this->value->serialize().$this->parameters->toHttpValue();
    }

    /**
     * @return array{0:SfItemInput, 1:MemberOrderedMap<string, SfItem>}
     */
    public function toPair(): array
    {
        return [$this->value->value, $this->parameters];
    }

    public function withValue(mixed $value): static
    {
        $value = new Value($value);
        if ($value->equals($this->value)) {
            return $this;
        }

        return new self($value, $this->parameters);
    }

    public function withParameters(Parameters $parameters): static
    {
        return $this->parameters->toHttpValue() === $parameters->toHttpValue() ? $this : new static($this->value, $parameters);
    }

    public function addParameter(MapKey|string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->add($key instanceof MapKey ? $key->value : $key, $member));
    }

    public function prependParameter(MapKey|string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key instanceof MapKey ? $key->value : $key, $member));
    }

    public function appendParameter(MapKey|string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->append($key instanceof MapKey ? $key->value : $key, $member));
    }

    public function withoutParameter(MapKey|string ...$keys): static
    {
        return $this->withParameters($this->parameters()->remove(
            ...array_map(fn (MapKey|string $key): string => $key instanceof MapKey ? $key->value : $key, $keys)
        ));
    }

    public function withoutAnyParameter(): static
    {
        return $this->withParameters(Parameters::create());
    }
}
