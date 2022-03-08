<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

interface SupportsParameters
{
    public function parameters(): Parameters;
}
