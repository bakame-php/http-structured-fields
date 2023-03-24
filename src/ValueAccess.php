<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;

/**
 * @phpstan-import-type SfTypeInput from StructuredField
 */
interface ValueAccess extends StructuredField
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
     * @param ValueAccess|SfTypeInput $value
     */
    public function withValue(mixed $value): static;
}