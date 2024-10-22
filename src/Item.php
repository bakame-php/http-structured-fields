<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;

use function array_is_list;
use function count;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
 *
 * @phpstan-import-type SfItemInput from StructuredField
 * @phpstan-import-type SfItemPair from StructuredField
 * @phpstan-import-type SfTypeInput from StructuredField
 */
final class Item implements ParameterAccess, StructuredField
{
    private function __construct(
        private readonly Value $value,
        private readonly Parameters $parameters
    ) {
    }

    public static function fromRfc9651(Stringable|string $httpValue): self
    {
        return self::fromHttpValue($httpValue, Ietf::Rfc9651);
    }

    public static function fromRfc8941(Stringable|string $httpValue): self
    {
        return self::fromHttpValue($httpValue, Ietf::Rfc8941);
    }

    /**
     * Returns a new instance from an HTTP Header or Trailer value string
     * in compliance with RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
     *
     * @throws SyntaxError If the HTTP value can not be parsed
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): self
    {
        return self::fromAssociative(...Parser::new($rfc)->parseItem($httpValue));
    }

    /**
     * Returns a new instance from a value type and an iterable of key-value parameters.
     *
     * @param iterable<string, SfItemInput> $parameters
     *
     * @throws SyntaxError If the value or the parameters are not valid
     */
    public static function fromAssociative(ByteSequence|Token|DisplayString|DateTimeInterface|string|int|float|bool $value, iterable $parameters): self
    {
        if (!$parameters instanceof Parameters) {
            $parameters = Parameters::fromAssociative($parameters);
        }

        return new self(new Value($value), $parameters);
    }

    /**
     * @param array{0: SfTypeInput, 1: Parameters|iterable<array{0:string, 1:SfItemInput}>} $pair
     *
     * @throws SyntaxError If the pair or its content is not valid.
     */
    public static function fromPair(array $pair): self
    {
        return match (true) {
            [] === $pair, !array_is_list($pair) => throw new SyntaxError('The pair must be represented by an non-empty array as a list.'), // @phpstan-ignore-line
            2 !== count($pair) => throw new SyntaxError('The pair first member is the item value; its second member is the item parameters.'), // @phpstan-ignore-line
            default => new self(new Value($pair[0]), $pair[1] instanceof Parameters ? $pair[1] : Parameters::fromPairs($pair[1])),
        };
    }

    /**
     * Returns a new bare instance from value.
     *
     * @param SfTypeInput|array{0:SfTypeInput, 1:Parameters|iterable<array{0:string, 1:SfItemInput}>} $value
     *
     * @throws SyntaxError If the value is not valid.
     */
    public static function new(mixed $value): self
    {
        if (is_array($value)) {
            return self::fromPair($value);
        }

        return self::fromValue(new Value($value));
    }

    /**
     * Returns a new bare instance from value.
     */
    private static function fromValue(Value $value): self
    {
        return new self($value, Parameters::new());
    }

    /**
     * Returns a new instance from a string.
     *
     * @throws SyntaxError if the string is invalid
     */
    public static function fromString(Stringable|string $value): self
    {
        return self::fromValue(Value::fromString($value));
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromEncodedByteSequence(Stringable|string $value): self
    {
        return self::fromValue(Value::fromEncodedByteSequence($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromDecodedByteSequence(Stringable|string $value): self
    {
        return self::fromValue(Value::fromDecodedByteSequence($value));
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromEncodedDisplayString(Stringable|string $value): self
    {
        return self::fromValue(Value::fromEncodedDisplayString($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromDecodedDisplayString(Stringable|string $value): self
    {
        return self::fromValue(Value::fromDecodedDisplayString($value));
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the token is invalid
     */
    public static function fromToken(Stringable|string $value): self
    {
        return self::fromValue(Value::fromToken($value));
    }

    /**
     * Returns a new instance from a timestamp and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the timestamp value is not supported
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
     * Returns a new instance from a DateTineInterface implementing object.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDate(DateTimeInterface $datetime): self
    {
        return self::fromValue(Value::fromDate($datetime));
    }

    /**
     * Returns a new instance from a float value.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDecimal(int|float $value): self
    {
        return self::fromValue(Value::fromDecimal($value));
    }

    /**
     * Returns a new instance from an integer value.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromInteger(int|float $value): self
    {
        return self::fromValue(Value::fromInteger($value));
    }

    /**
     * Returns a new instance for the boolean true type.
     */
    public static function true(): self
    {
        return self::fromValue(Value::true());
    }

    /**
     * Returns a new instance for the boolean false type.
     */
    public static function false(): self
    {
        return self::fromValue(Value::false());
    }

    /**
     * Returns the underlying value.
     */
    public function value(): ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool
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

    public function parameter(string $key): ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool|null
    {
        try {
            return $this->parameters->get($key)->value();
        } catch (StructuredFieldError) {
            return null;
        }
    }

    /**
     * @return array{0:string, 1:Token|ByteSequence|DisplayString|DateTimeImmutable|int|float|string|bool}|array{}
     */
    public function parameterByIndex(int $index): array
    {
        try {
            $tuple = $this->parameters->pair($index);

            return [$tuple[0], $tuple[1]->value()];
        } catch (StructuredFieldError) {
            return [];
        }
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.1
     */
    public function toHttpValue(?Ietf $rfc = null): string
    {
        $rfc ??= Ietf::Rfc9651;


        return $this->value->serialize($rfc).$this->parameters->toHttpValue($rfc);
    }

    public function toRfc9651(): string
    {
        return $this->toHttpValue(Ietf::Rfc9651);
    }

    public function toRfc8941(): string
    {
        return $this->toHttpValue(Ietf::Rfc8941);
    }

    public function __toString(): string
    {
        return $this->toHttpValue();
    }

    /**
     * @return array{0:SfItemInput, 1:Parameters}
     */
    public function toPair(): array
    {
        return [$this->value->value, $this->parameters];
    }

    /**
     * Returns a new instance with the newly associated value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified value change.
     *
     * @throws SyntaxError If the value is invalid or not supported
     */
    public function withValue(
        DateTimeInterface|ByteSequence|Token|DisplayString|string|int|float|bool $value
    ): static {
        $value = new Value($value);
        if ($value->equals($this->value)) {
            return $this;
        }

        return new self($value, $this->parameters);
    }

    public function sortParameters(Closure $callback): static
    {
        return $this->withParameters($this->parameters()->sort($callback));
    }

    public function withParameters(Parameters $parameters): static
    {
        return $this->parameters->toHttpValue() === $parameters->toHttpValue() ? $this : new self($this->value, $parameters);
    }

    public function addParameter(
        string $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        return $this->withParameters($this->parameters()->add($key, $member));
    }

    public function prependParameter(
        string $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(
        string $key,
        StructuredFieldProvider|StructuredField|Token|ByteSequence|DisplayString|DateTimeInterface|string|int|float|bool $member
    ): static {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function pushParameters(array ...$pairs): static
    {
        return $this->withParameters($this->parameters()->push(...$pairs));
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function unshiftParameters(array ...$pairs): static
    {
        return $this->withParameters($this->parameters()->unshift(...$pairs));
    }

    /**
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function insertParameters(int $index, array ...$pairs): static
    {
        return $this->withParameters($this->parameters()->insert($index, ...$pairs));
    }

    /**
     * @param array{0:string, 1:SfItemInput} $pair
     */
    public function replaceParameter(int $index, array $pair): static
    {
        return $this->withParameters($this->parameters()->replace($index, $pair));
    }

    public function withoutParameterByKeys(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->removeByKeys(...$keys));
    }

    public function withoutParameterByIndices(int ...$indices): static
    {
        return $this->withParameters($this->parameters()->removeByIndices(...$indices));
    }

    public function withoutAnyParameter(): static
    {
        return $this->withParameters(Parameters::new());
    }

    public function filterParameters(Closure $callback): static
    {
        return $this->withParameters($this->parameters()->filter($callback));
    }
}
