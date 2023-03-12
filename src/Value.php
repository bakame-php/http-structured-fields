<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;

/**
 * @phpstan-type DataType ByteSequence|Token|\DateTimeInterface|\Stringable|string|int|float|bool
 */
interface Value extends ParameterAccess, StructuredField
{
    /**
     * Returns the underlying value.
     */
    public function value(): ByteSequence|Token|DateTimeImmutable|string|int|float|bool;

    /**
     * Returns the value type.
     */
    public function type(): Type;

    /**
     * Returns a new instance with the newly associated value.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified value change.
     *
     * @param Value|DataType $value
     */
    public function withValue(mixed $value): static;
}
