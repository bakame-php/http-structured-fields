<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface SupportsParameters
{
    public function parameters(): Parameters;

    /**
     * @throws SyntaxError   if the key used is invalid according to RFC8941
     * @throws InvalidOffset If no value is found for the given key
     */
    public function parameter(string $key): Item|Token|ByteSequence|float|int|bool|string;

    public function exchangeParameters(Parameters $parameters): void;
}
