<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use Throwable;

use function base64_decode;
use function base64_encode;
use function preg_match;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3.5
 */
final class ByteSequence
{
    private function __construct(
        private readonly string $value
    ) {
    }

    /**
     * Returns a new instance from a Base64 encoded string.
     */
    public static function fromEncoded(Stringable|string $encoded): self
    {
        $encoded = (string) $encoded;
        if (1 !== preg_match('/^[a-z\d+\/=]*$/i', $encoded)) {
            throw new SyntaxError('The byte sequence '.$encoded.' contains invalid characters.');
        }

        $decoded = base64_decode($encoded, true);
        if (false === $decoded) {
            throw new SyntaxError('Unable to base64 decode the byte sequence '.$encoded);
        }

        return new self($decoded);
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
        return base64_encode($this->value);
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    public function type(): Type
    {
        return Type::ByteSequence;
    }
}
