<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface StructuredFieldProvider
{
    /**
     * Returns an object implementing the StructuredField interface.
     */
    public function toStructuredField(): StructuredField;
}
