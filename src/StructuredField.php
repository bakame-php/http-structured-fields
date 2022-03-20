<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface StructuredField
{
    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     */
    public function toHttpValue(): string;
}
