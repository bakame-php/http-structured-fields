<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface StructuredFieldAccess
{
    /**
     * Returns an object implementing the StructuredField interface.
     */
    public function toStructuredField(): StructuredField;
}
