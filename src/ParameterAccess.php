<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface ParameterAccess
{
    public function parameters(): Parameters;

    public function withParameters(Parameters $parameters): static;
}
