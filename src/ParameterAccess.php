<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;

/**
 * @phpstan-import-type SfItem from StructuredField
 * @phpstan-import-type SfType from StructuredField
 *
 * @method array{0:string, 1:SfType}|array{} parameterByIndex(int $index) returns the tuple representation of the parameter
 * @method static pushParameters(array ...$pairs) Inserts pair at the end of the member list
 * @method static unshiftParameters(array ...$pairs) Inserts pair at the start of the member list
 * @method static insertParameters(int $index, array ...$pairs) Inserts pairs at the index
 * @method static replaceParameter(int $index, array $pair) Inserts pair at the end of the member list
 * @method static withoutParameterByKeys(string ...$keys) Remove members associated with the list of submitted keys in the associated parameter instance.
 * @method static withoutParameterByIndices(int ...$indices) Remove parameters using their position
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
     */
    public function parameter(string $key): Token|ByteSequence|DateTimeImmutable|int|float|string|bool|null;

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
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 1.1
     * @see ParameterAccess::withoutParameterByKeys()
     *
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
