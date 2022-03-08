<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

interface StructuredField
{
    /**
     * Returns the serialize-representation of the Structured Field in textual HTTP field values.
     */
    public function canonical(): string;
}
