<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface StructuredField
{
    /**
     * Returns the serialize-representation of the Structured Field as a textual HTTP field value.
     *
     * @throws ForbiddenStateError If a component of the object is in invalid state
     */
    public function toHttpValue(): string;
}
