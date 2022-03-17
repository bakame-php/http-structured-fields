<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

final class ByteSequence implements StructuredField
{
    private function __construct(private string $value)
    {
    }

    /**
     * @param array{value:string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['value']);
    }

    /**
     * Returns a new instance from a Base64 encoded string.
     */
    public static function fromEncoded(string $encodedValue): self
    {
        if (1 !== preg_match('/^(?<bytes>[a-z0-9+\/=]*)$/i', $encodedValue, $matches)) {
            throw new SyntaxError('Invalid character in byte sequence');
        }

        /** @var string $decoded */
        $decoded = base64_decode($matches['bytes'], true);

        return new self($decoded);
    }

    /**
     * Returns a new instance from a raw decoded string.
     */
    public static function fromDecoded(string $value): self
    {
        return new self($value);
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
