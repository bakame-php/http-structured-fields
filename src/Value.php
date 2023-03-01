<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

interface Value extends ParameterAccess, StructuredField
{
    /**
     * Returns the underlying value.
     */
    public function value(): mixed;

    /**
     * Returns the value type.
     */
    public function type(): Type;

    /**
     * Returns a new instance with the newly associated value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified value change.
     */
    public function withValue(mixed $value): static;
}
