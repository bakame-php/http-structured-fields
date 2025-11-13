<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use BadMethodCallException;
use Bakame\Http\StructuredFields\Validation\Violation;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use ReflectionMethod;
use Stringable;
use Throwable;
use TypeError;

use function array_is_list;
use function count;
use function in_array;

use const JSON_PRESERVE_ZERO_FRACTION;
use const PHP_ROUND_HALF_EVEN;

/**
 * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-3.3
 *
 * @phpstan-import-type SfType from StructuredFieldProvider
 * @phpstan-import-type SfItemInput from StructuredFieldProvider
 * @phpstan-import-type SfItemPair from StructuredFieldProvider
 * @phpstan-import-type SfTypeInput from StructuredFieldProvider
 *
 * @method static ?Item tryFromPair(array{0: SfItemInput, 1?: Parameters|iterable<array{0:string, 1:SfItemInput}>}|array<mixed> $pair) try to create a new instance from a Pair
 * @method static ?Item tryFromRfc9651(Stringable|string $httpValue) try to create a new instance from a string using RFC9651
 * @method static ?Item tryFromRfc8941(Stringable|string $httpValue) try to create a new instance from a string using RFC8941
 * @method static ?Item tryFromHttpValue(Stringable|string $httpValue) try to create a new instance from a string
 * @method static ?Item tryFromAssociative(Bytes|Token|DisplayString|DateTimeInterface|string|int|float|bool $value, StructuredFieldProvider|Parameters|iterable<string, SfItemInput> $parameters) try to create a new instance from a value and a parameters as associative construct
 * @method static ?Item tryNew(mixed $value) try to create a new bare instance from a value
 * @method static ?Item tryFromEncodedBytes(Stringable|string $value) try to create a new instance from an encoded byte sequence
 * @method static ?Item tryFromDecodedBytes(Stringable|string $value) try to create a new instance from a decoded byte sequence
 * @method static ?Item tryFromEncodedDisplayString(Stringable|string $value) try to create a new instance from an encoded display string
 * @method static ?Item tryFromDecodedDisplayString(Stringable|string $value) try to create a new instance from a decoded display string
 * @method static ?Item tryFromToken(Stringable|string $value) try to create a new instance from a token string
 * @method static ?Item tryFromTimestamp(int $timestamp) try to create a new instance from a timestamp
 * @method static ?Item tryFromDateFormat(string $format, string $datetime) try to create a new instance from a date format
 * @method static ?Item tryFromDateString(string $datetime, DateTimeZone|string|null $timezone = null) try to create a new instance from a date string
 * @method static ?Item tryFromDate(DateTimeInterface $datetime) try to create a new instance from a DateTimeInterface object
 * @method static ?Item tryFromDecimal(int|float $value) try to create a new instance from a float
 * @method static ?Item tryFromInteger(int|float $value) try to create a new instance from an integer
 */
final class Item
{
    use ParameterAccess;

    private readonly Token|Bytes|DisplayString|DateTimeImmutable|int|float|string|bool $value;
    private readonly Parameters $parameters;
    private readonly Type $type;

    private function __construct(Token|Bytes|DisplayString|DateTimeInterface|int|float|string|bool $value, ?Parameters $parameters = null)
    {
        if ($value instanceof DateTimeInterface && !$value instanceof DateTimeImmutable) {
            $value = DateTimeImmutable::createFromInterface($value);
        }

        $this->value = $value;
        $this->parameters = $parameters ?? Parameters::new();
        $this->type = Type::fromVariable($value);
    }

    /**
     * @throws BadMethodCallException
     */
    public static function __callStatic(string $name, array $arguments): ?self  /* @phpstan-ignore-line */
    {
        if (!str_starts_with($name, 'try')) {
            throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.');
        }

        $namedConstructor = lcfirst(substr($name, strlen('try')));
        if (!method_exists(self::class, $namedConstructor)) {
            throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" does not exist.');
        }

        $method = new ReflectionMethod(self::class, $namedConstructor);
        if (!$method->isPublic() || !$method->isStatic()) {
            throw new BadMethodCallException('The method "'.self::class.'::'.$name.'" can not be accessed directly.');
        }

        try {
            return self::$namedConstructor(...$arguments); /* @phpstan-ignore-line */
        } catch (Throwable) {  /* @phpstan-ignore-line */
            return null;
        }
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
     * @throws SyntaxError|Exception If the HTTP value can not be parsed
     */
    public static function fromHttpValue(Stringable|string $httpValue, Ietf $rfc = Ietf::Rfc9651): self
    {
        return self::fromPair((new Parser($rfc))->parseItem($httpValue));
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
            if ($parameters instanceof Parameters) {
                return new self($value, $parameters);
            }

            throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$parameters::class.' given.');
        }

        if (!$parameters instanceof Parameters) {
            return new self($value, Parameters::fromAssociative($parameters));
        }

        return new self($value, $parameters);
    }

    /**
     * @param array{0: SfItemInput, 1?: Parameters|iterable<array{0:string, 1:SfItemInput}>}|array<mixed> $pair
     *
     * @throws SyntaxError If the pair or its content is not valid.
     */
    public static function fromPair(array $pair): self
    {
        $nbElements = count($pair);
        if (!in_array($nbElements, [1, 2], true) || !array_is_list($pair)) {
            throw new SyntaxError('The pair must be represented by an non-empty array as a list containing exactly 1 or 2 members.');
        }

        if (1 === $nbElements) {
            return new self($pair[0]);
        }

        if ($pair[1] instanceof StructuredFieldProvider) {
            $pair[1] = $pair[1]->toStructuredField();
            if ($pair[1] instanceof Parameters) {
                return new self($pair[0], Parameters::fromPairs($pair[1]));
            }

            throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Parameters::class.'; '.$pair[1]::class.' given.');
        }

        if (!$pair[1] instanceof Parameters) {
            return new self($pair[0], Parameters::fromPairs($pair[1]));
        }

        return new self($pair[0], $pair[1]);
    }

    /**
     * Returns a new bare instance from value.
     *
     * @param SfItemPair|SfItemInput $value
     *
     * @throws SyntaxError|TypeError If the value is not valid.
     */
    public static function new(mixed $value): self
    {
        if (is_array($value)) {
            return self::fromPair($value);
        }

        return new self($value); /* @phpstan-ignore-line */
    }

    /**
     * Returns a new instance from a string.
     *
     * @throws SyntaxError if the string is invalid
     */
    public static function fromString(Stringable|string $value): self
    {
        return new self((string)$value);
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromEncodedBytes(Stringable|string $value): self
    {
        return new self(Bytes::fromEncoded($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromDecodedBytes(Stringable|string $value): self
    {
        return new self(Bytes::fromDecoded($value));
    }

    /**
     * Returns a new instance from an encoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromEncodedDisplayString(Stringable|string $value): self
    {
        return new self(DisplayString::fromEncoded($value));
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the sequence is invalid
     */
    public static function fromDecodedDisplayString(Stringable|string $value): self
    {
        return new self(DisplayString::fromDecoded($value));
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the token is invalid
     */
    public static function fromToken(Stringable|string $value): self
    {
        return new self(Token::fromString($value));
    }

    /**
     * Returns a new instance from a timestamp and an iterable of key-value parameters.
     *
     * @throws SyntaxError if the timestamp value is not supported
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
        $value instanceof DateTimeImmutable || throw new SyntaxError('The date notation `'.$datetime.'` is incompatible with the date format `'.$format.'`.');

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
            return new self(new DateTimeImmutable($datetime, $timezone));
        } catch (Throwable $exception) {
            throw new SyntaxError('Unable to create a '.DateTimeImmutable::class.' instance with the date notation `'.$datetime.'.`', 0, $exception);
        }
    }

    /**
     * Returns a new instance from a DateTineInterface implementing object.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDate(DateTimeInterface $datetime): self
    {
        return new self($datetime);
    }

    /**
     * Returns a new instance from a float value.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromDecimal(int|float $value): self
    {
        return new self((float)$value);
    }

    /**
     * Returns a new instance from an integer value.
     *
     * @throws SyntaxError if the format is invalid
     */
    public static function fromInteger(int|float $value): self
    {
        return new self((int)$value);
    }

    /**
     * Returns a new instance for the boolean true type.
     */
    public static function true(): self
    {
        return new self(true);
    }

    /**
     * Returns a new instance for the boolean false type.
     */
    public static function false(): self
    {
        return new self(false);
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
        if (null === $validate) {
            return $this->value;
        }

        $exceptionMessage = $validate($this->value);
        if (true === $exceptionMessage) {
            return $this->value;
        }

        if (!is_string($exceptionMessage) || '' === trim($exceptionMessage)) {
            $exceptionMessage = "The item value '{value}' failed validation.";
        }

        throw new Violation(strtr($exceptionMessage, ['{value}' => $this->serialize()]));
    }

    public function type(): Type
    {
        return $this->type;
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.1
     */
    public function toHttpValue(Ietf $rfc = Ietf::Rfc9651): string
    {
        return $this->serialize($rfc).$this->parameters->toHttpValue($rfc);
    }

    /**
     * Serialize the Item value according to RFC8941.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9651.html#section-4.1
     */
    private function serialize(Ietf $rfc = Ietf::Rfc9651): string
    {
        return match (true) {
            !$rfc->supports($this->type) => throw MissingFeature::dueToLackOfSupport($this->type, $rfc),
            $this->value instanceof DateTimeImmutable => '@'.$this->value->getTimestamp(),
            $this->value instanceof Token => $this->value->toString(),
            $this->value instanceof Bytes => ':'.$this->value->encoded().':',
            $this->value instanceof DisplayString => '%"'.$this->value->encoded().'"',
            is_int($this->value) => (string) $this->value,
            is_float($this->value) => (string) json_encode(round($this->value, 3, PHP_ROUND_HALF_EVEN), JSON_PRESERVE_ZERO_FRACTION),
            $this->value,
            false === $this->value => '?'.($this->value ? '1' : '0'),
            default => '"'.preg_replace('/(["\\\])/', '\\\$1', $this->value).'"',
        };
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
        return [$this->value, $this->parameters];
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
    public function withValue(DateTimeInterface|Bytes|Token|DisplayString|string|int|float|bool $value): self
    {
        $isEqual = match (true) {
            $this->value instanceof Bytes,
            $this->value instanceof Token,
            $this->value instanceof DisplayString => $this->value->equals($value),
            $this->value instanceof DateTimeInterface && $value instanceof DateTimeInterface => $value->getTimestamp() === $this->value->getTimestamp(),
            default => $value === $this->value,
        };

        if ($isEqual) {
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
