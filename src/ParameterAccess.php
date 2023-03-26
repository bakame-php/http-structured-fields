<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @phpstan-import-type SfTypeInput from StructuredField
 * @phpstan-import-type SfItem from StructuredField
 */
interface ParameterAccess
{
    /**
     * @return array{0:mixed, 1:MemberOrderedMap<string, SfItem>}
     */
    public function toPair(): array;

    /**
     * Returns a copy of the associated parameter instance.
     */
    public function parameters(): Parameters;

    /**
     * Returns the member value or null if no members value exists.
     *
     * @return SfTypeInput|null
     */
    public function parameter(string $key): mixed;

    /**
     * Adds a member if its key is not present at the of the associated parameter instance or update the instance at the given key.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function addParameter(string $key, ValueAccess $member): static;

    /**
     * Adds a member at the start of the associated parameter instance and deletes any previous reference to the key if present.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function prependParameter(string $key, ValueAccess $member): static;

    /**
     * Adds a member at the end of the associated parameter instance and deletes any previous reference to the key if present.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function appendParameter(string $key, ValueAccess $member): static;

    /**
     * Deletes members associated with the list of submitted keys in the associated parameter intance.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     */
    public function withoutParameter(string ...$keys): static;

    /**
     * Removes all parameters members associated with the list of submitted keys in the associated parameter intance.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     */
    public function withoutAnyParameter(): static;

    /**
     * Returns a new instance with the newly associated parameter instance.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     */
    public function withParameters(Parameters $parameters): static;
}
