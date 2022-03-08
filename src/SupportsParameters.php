<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface SupportsParameters
{
    public function parameters(): Parameters;
}
