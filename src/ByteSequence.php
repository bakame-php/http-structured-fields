<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

final class ByteSequence implements StructuredField
{
    public static function fromEncoded(string $encodedValue): self
    {
        if (1 !== preg_match('/^(?<bytes>[a-z0-9+\/=]*)$/i', $encodedValue, $matches)) {
            throw new SyntaxError('Invalid character in byte sequence');
        }

        /** @var string $decoded */
        $decoded = base64_decode($matches['bytes'], true);

        return new self($decoded);
    }

    public static function fromDecoded(string $value): self
    {
        return new self($value);
    }

    private function __construct(private string $value)
    {
    }

    public function decoded(): string
    {
        return $this->value;
    }

    public function encoded(): string
    {
        return  base64_encode($this->value);
    }

    public function canonical(): string
    {
        return ':'.$this->encoded().':';
    }
}
