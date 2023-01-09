<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use function base64_decode;
use function base64_encode;
use function preg_match;

final class ByteSequence
{
    private function __construct(
        private readonly string $value
    ) {
    }

    /**
     * Returns a new instance from a Base64 encoded string.
     */
    public static function fromEncoded(Stringable|string $encodedValue): self
    {
        $encodedValue = (string) $encodedValue;
        if (1 !== preg_match('/^[a-z\d+\/=]*$/i', $encodedValue)) {
            throw new SyntaxError('Invalid character in byte sequence.');
        }

        $decoded = base64_decode($encodedValue, true);
        if (false === $decoded) {
            throw new SyntaxError('Unable to base64 decode the byte sequence.');
        }

        return new self($decoded);
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
        return base64_encode($this->value);
    }
}
