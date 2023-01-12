<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface ParameterAccess
{
    public function parameters(): Parameters;

    public function prependParameter(string $name, Item|ByteSequence|Token|bool|int|float|string $member): static;

    public function appendParameter(string $name, Item|ByteSequence|Token|bool|int|float|string $member): static;

    public function withoutParameter(string $name): static;

    public function withParameters(Parameters $parameters): static;
}
