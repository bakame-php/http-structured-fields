<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use Throwable;
use function count;
use function preg_match;
use function str_contains;
use function strlen;
use function substr;
use function trim;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
 * @phpstan-import-type DataType from Value
 */
final class Item implements Value
{
    private readonly Token|ByteSequence|DateTimeImmutable|int|float|string|bool $value;
    private readonly Type $type;

    private function __construct(mixed $value, private readonly Parameters $parameters)
    {
        $this->value = Type::convert($value);
        $this->type = Type::fromValue($this->value);
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

        return new self($value, $parameters);
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
            default => new self($pair[0], Parameters::fromPairs($pair[1])),
        };
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
     */
    public static function fromEncodedByteSequence(Stringable|string $value): self
    {
        return new self(ByteSequence::fromEncoded($value), Parameters::create());
    }

    /**
     * Returns a new instance from a decoded byte sequence and an iterable of key-value parameters.
     */
    public static function fromDecodedByteSequence(Stringable|string $value): self
    {
        return new self(ByteSequence::fromDecoded($value), Parameters::create());
    }

    /**
     * Returns a new instance from a Token and an iterable of key-value parameters.
     */
    public static function fromToken(Stringable|string $value): self
    {
        return new self(Token::fromString($value), Parameters::create());
    }

    /**
     * Returns a new instance from a timestamp and an iterable of key-value parameters.
     */
    public static function fromTimestamp(int $timestamp): self
    {
        return new self((new DateTimeImmutable())->setTimestamp($timestamp), Parameters::create());
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

        return new self($value, Parameters::create());
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

        return new self($value, Parameters::create());
    }

    public function value(): ByteSequence|Token|DateTimeImmutable|string|int|float|bool
    {
        return $this->value;
    }

    public function type(): Type
    {
        return $this->type;
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
        return Type::serialize($this->value).$this->parameters->toHttpValue();
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
            default => new self($value, $this->parameters),
        };
    }

    public function withParameters(Parameters $parameters): static
    {
        return $this->parameters->toHttpValue() === $parameters->toHttpValue() ? $this : new static($this->value, $parameters);
    }

    public function addParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->add($key, $member));
    }

    public function prependParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function withoutParameter(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->remove(...$keys));
    }

    public function appendParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    public function withoutAnyParameter(): static
    {
        return $this->withParameters(Parameters::create());
    }
}
