<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;

/**
 * @phpstan-import-type SfItem from StructuredField
 * @phpstan-import-type SfType from StructuredField
 * @phpstan-import-type SfItemInput from StructuredField
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
    public function parameter(string $key): ByteSequence|Token|DisplayString|DateTimeImmutable|string|int|float|bool|null;

    /**
     * Returns the member value and name as pair or an emoty array if no members value exists.
     *
     * @return array{0:string, 1:Token|ByteSequence|DisplayString|DateTimeImmutable|int|float|string|bool}|array{}
     */
    public function parameterByIndex(int $index): array;

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

    /**
     * Inserts pair at the end of the member list.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function pushParameters(array ...$pairs): static;

    /**
     * Inserts pair at the beginning of the member list.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function unshiftParameters(array ...$pairs): static;

    /**
     * Delete member based on their name.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     */
    public function withoutParameterByKeys(string ...$keys): static;

    /**
     * Delete member based on their offsets.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     */
    public function withoutParameterByIndices(int ...$indices): static;

    /**
     * Inserts members at the specified index.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param array{0:string, 1:SfItemInput} ...$pairs
     */
    public function insertParameters(int $index, array ...$pairs): static;

    /**
     * Replace the member at the specified index.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param array{0:string, 1:SfItemInput} $pair
     */
    public function replaceParameter(int $index, array $pair): static;
}
