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

    public static function tryFromEncoded(Stringable|string $encoded): ?self
    {
        try {
            return self::fromEncoded($encoded);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns a new instance from a Base64 encoded string.
     */
    public static function fromEncoded(Stringable|string $encoded): self
    {
        $encoded = (string) $encoded;

        if (1 === preg_match('/[^\x20-\x7E]/i', $encoded)) {
            throw new SyntaxError('The display string '.$encoded.' contains invalid characters.');
        }

        if (!str_contains($encoded, '%')) {
            return new self($encoded);
        }

        if (1 === preg_match('/%(?![0-9a-f]{2})/', $encoded)) {
            throw new SyntaxError('The display string '.$encoded.' contains invalid utf-8 encoded sequence.');
        }

        $decoded = (string) preg_replace_callback(
            ',%[a-f0-9]{2},',
            fn (array $matches): string => rawurldecode($matches[0]),
            $encoded
        );

        if (1 !== preg_match('//u', $decoded)) {
            throw new SyntaxError('The display string '.$encoded.' contains invalid characters.');
        }

        return new self($decoded);
    }

    /**
     * Returns a new instance from a raw decoded string.
     */
    public static function fromDecoded(Stringable|string $decoded): self
    {
        return new self((string) $decoded);
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
        return (string) preg_replace_callback(
            '/[%"\x00-\x1F\x7F-\xFF]/',
            static fn (array $matches): string => strtolower(rawurlencode($matches[0])),
            $this->value
        );
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
