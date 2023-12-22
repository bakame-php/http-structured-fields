<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use Throwable;

use function preg_match;
use function preg_replace_callback;
use function rawurldecode;
use function rawurlencode;

/**
 * @see https://datatracker.ietf.org/doc/html/draft-ietf-httpbis-sfbis#section-4.2.10
 */
final class DisplayString
{
    private function __construct(
        private readonly string $value
    ) {
    }

    public static function tryFromEncoded(Stringable|string $encodedValue): ?self
    {
        try {
            return self::fromEncoded($encodedValue);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns a new instance from a Base64 encoded string.
     */
    public static function fromEncoded(Stringable|string $encodedValue): self
    {
        $value = (string) $encodedValue;

        if (1 === preg_match('/[^\x20-\x7E]/i', $value)) {
            throw new SyntaxError('The string contains invalid characters.'.$value);
        }

        if (!str_contains($value, '%')) {
            return new self($value);
        }

        if (1 === preg_match('/%(?![0-9a-fA-F]{2})/', $value)) {
            throw new SyntaxError('The string '.$value.' contains invalid utf-8 encoded sequence.');
        }

        $value = (string) preg_replace_callback(',%[A-Fa-f0-9]{2},', fn (array $matches) =>  rawurldecode($matches[0]), $value);
        if (1 !== preg_match('//u', $value)) {
            throw new SyntaxError('The string contains invalid characters.'.$value);
        }

        return new self($value);
    }

    /**
     * Returns a new instance from a raw decoded string.
     */
    public static function fromDecoded(Stringable|string $value): self
    {
        return new self((string) $value);
    }

    /**
     * Returns the decoded string.
     */
    public function decoded(): string
    {
        return $this->value;
    }

    /**
     * Returns the base64 encoded string.
     */
    public function encoded(): string
    {
        $value = $this->value;
        $encodeMatches = static fn (array $matches): string => match (1) {
            preg_match('/[^A-Za-z\d_\-.~]/', rawurldecode($matches[0])) => rawurlencode($matches[0]),
            default => $matches[0],
        };

        return strtolower(match (true) {
            '' === $value => $value,
            default => (string) preg_replace_callback(
                '/[^A-Za-z\d_\-.~\!\$&\'\(\)\*\+,;\=%\\\\:@\/? ]+|%(?![A-Fa-f\d]{2})/',
                $encodeMatches(...),
                $value
            ),
        });
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    public function type(): Type
    {
        return Type::DisplayString;
    }
}
