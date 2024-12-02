<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Bakame\Http\StructuredFields\Validation\Violation;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;

use function array_is_list;
use function count;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
 *
 * @phpstan-import-type SfType from StructuredFieldProvider
 * @phpstan-import-type SfItemInput from StructuredFieldProvider
 * @phpstan-import-type SfItemPair from StructuredFieldProvider
 * @phpstan-import-type SfTypeInput from StructuredFieldProvider
 */
final class Item
{
    use ParameterAccess;

    private function __construct(private readonly Value $value, private readonly Parameters $parameters)
    {
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
     * in compliance with a published RFC.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
     *
     * @throws SyntaxError If the HTTP value can not be parsed
     */
    public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = Ietf::Rfc9651): self
    {
        return self::fromPair(Parser::new($rfc)->parseItem($httpValue));
    }

    /**
     * Returns a new instance from a value type and an iterable of key-value parameters.
     *
     * @param StructuredFieldProvider|Parameters|iterable<string, SfItemInput> $parameters
     *
     * @throws SyntaxError If the value or the parameters are not valid
     */
    public static function fromAssociative(
        Bytes|Token|DisplayString|DateTimeInterface|string|int|float|bool $value,
        StructuredFieldProvider|Parameters|iterable $parameters
    ): self {
        if ($parameters instanceof StructuredFieldProvider) {
            $parameters = $parameters->toStructuredField();
            if (!$parameters instanceof Parameters) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$parameters::class.' given.');
            }
        }

        if (!$parameters instanceof Parameters) {
            $parameters = Parameters::fromAssociative($parameters);
        }

        return new self(new Value($value), $parameters);
    }

    /**
     * @param array{0: SfItemInput, 1?: Parameters|iterable<array{0:string, 1:SfItemInput}>}|array<mixed> $pair
     */
    public static function tryFromPair(array $pair): ?self
    {
        try {
            return self::fromPair($pair);
        } catch (StructuredFieldError) {
            return null;
        }
    }

    /**
     * @param array{0: SfItemInput, 1?: Parameters|iterable<array{0:string, 1:SfItemInput}>}|array<mixed> $pair
     *
     * @throws SyntaxError If the pair or its content is not valid.
     */
    public static function fromPair(array $pair): self
    {
        if ([] === $pair || !array_is_list($pair) || 2 < count($pair)) {
            throw new SyntaxError('The pair must be represented by an non-empty array as a list containing at most 2 members.');
        }

        if (1 === count($pair)) {
            return new self(new Value($pair[0]), Parameters::new());
        }

        if ($pair[1] instanceof StructuredFieldProvider) {
            $pair[1] = $pair[1]->toStructuredField();
        }

        if (!$pair[1] instanceof Parameters) {
            $pair[1] = Parameters::fromPairs($pair[1]);
        }

        return new self(new Value($pair[0]), $pair[1]);
    }

    /**
     * Returns a new bare instance from value.
     *
     * @param SfItemInput|array{0:SfItemInput, 1:Parameters|iterable<array{0:string, 1:SfItemInput}>} $value
     *
     * @throws SyntaxError If the value is not valid.
     */
    public static function new(mixed $value): self
    {
        if ($value instanceof Item) {
            return new self(new Value($value->value()), $value->parameters());
        }

        if (is_array($value)) {
            return self::fromPair($value);
        }

        return self::fromValue(new Value($value)); /* @phpstan-ignore-line */
    }

    /**
     * Returns a new bare instance from value or null on error.
     *
     * @param SfTypeInput|Item|array{0:SfTypeInput, 1:Parameters|iterable<array{0:string, 1:SfTypeInput}>} $value
     */
    public static function tryNew(StructuredFieldProvider|Item|DateTimeInterface|Bytes|DisplayString|Token|array|int|float|string|bool  $value): ?self
    {
        try {
            return self::new($value);
        } catch (SyntaxError) {
            return null;
        }
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
    public static function fromEncodedBytes(Stringable|string $value): self
    {
        return self::fromValue(Value::fromEncodedBytes($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromDecodedBytes(Stringable|string $value): self
    {
        return self::fromValue(Value::fromDecodedBytes($value));
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
     * If a validation rule is provided, an exception will be thrown
     * if the validation rules does not return true.
     *
     * if the validation returns false then a default validation message will be return; otherwise the submitted message string will be returned as is.
     *
     * @param ?callable(SfType): (string|bool) $validate
     *
     * @throws Violation
     */
    public function value(?callable $validate = null): Bytes|Token|DisplayString|DateTimeImmutable|string|int|float|bool
    {
        $value = $this->value->value;
        if (null === $validate) {
            return $value;
        }

        $exceptionMessage = $validate($value);
        if (true === $exceptionMessage) {
            return $value;
        }

        if (!is_string($exceptionMessage) || '' === trim($exceptionMessage)) {
            $exceptionMessage = "The item value '{value}' failed validation.";
        }

        throw new Violation(strtr($exceptionMessage, ['{value}' => $this->value->serialize()]));
    }

    public function type(): Type
    {
        return $this->value->type;
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.1
     */
    public function toHttpValue(?Ietf $rfc = Ietf::Rfc9651): string
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

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->toHttpValue() === $this->toHttpValue();
    }

    /**
     * Apply the callback if the given "condition" is (or resolves to) true.
     *
     * @param (callable($this): bool)|bool $condition
     * @param callable($this): (self|null) $onSuccess
     * @param ?callable($this): (self|null) $onFail
     */
    public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): self
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
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
        DateTimeInterface|Bytes|Token|DisplayString|string|int|float|bool $value
    ): self {
        $value = new Value($value);
        if ($value->equals($this->value)) {
            return $this;
        }

        return new self($value, $this->parameters);
    }

    public function withParameters(StructuredFieldProvider|Parameters $parameters): static
    {
        if ($parameters instanceof StructuredFieldProvider) {
            $parameters = $parameters->toStructuredField();
            if (!$parameters instanceof Parameters) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$parameters::class.' given.');
            }
        }

        return $this->parameters->equals($parameters) ? $this : new self($this->value, $parameters);
    }
}
