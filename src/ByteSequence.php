<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Stringable;
use function base64_decode;
use function base64_encode;
use function preg_match;

final class ByteSequence implements StructuredField
{
    private function __construct(
        private string $value
    ) {
    }

    /**
     * Returns a new instance from a Base64 encoded string.
     */
    public static function fromEncoded(Stringable|string $encodedValue): self
    {
        if (1 !== preg_match('/^(?<bytes>[a-z\d+\/=]*)$/i', (string) $encodedValue, $matches)) {
            throw new SyntaxError('Invalid character in byte sequence');
        }

        /** @var string $decoded */
        $decoded = base64_decode($matches['bytes'], true);

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
        return  base64_encode($this->value);
    }

    public function toHttpValue(): string
    {
        return ':'.$this->encoded().':';
    }
}
