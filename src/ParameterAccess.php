<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Closure;
use DateTimeImmutable;

/**
 * Common manipulation methods used when interacting with an object
 * with a Parameters instance attached to it.
 */
interface ParameterAccess
{
    /**
     * @return array{0:mixed, 1:Parameters}
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
    public function addParameter(string $key, Item $member): static;

    /**
     * Adds a member at the start of the associated parameter instance and deletes any previous reference to the key if present.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function prependParameter(string $key, Item $member): static;

    /**
     * Adds a member at the end of the associated parameter instance and deletes any previous reference to the key if present.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @throws SyntaxError If the string key is not a valid
     */
    public function appendParameter(string $key, Item $member): static;

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
     * @param array{0:string, 1:Item} ...$pairs
     */
    public function pushParameters(array ...$pairs): static;

    /**
     * Inserts pair at the beginning of the member list.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param array{0:string, 1:Item} ...$pairs
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
     * @param array{0:string, 1:Item} ...$pairs
     */
    public function insertParameters(int $index, array ...$pairs): static;

    /**
     * Replace the member at the specified index.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param array{0:string, 1:Item} $pair
     */
    public function replaceParameter(int $index, array $pair): static;

    /**
     * Sort the object parameters by value using a callback.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param Closure(array{0:string, 1:Item}, array{0:string, 1:Item}): int $callback
     */
    public function sortParameters(Closure $callback): static;

    /**
     * Filter the object parameters using a callback.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified parameter change.
     *
     * @param Closure(array{0:string, 1:Item}, int): bool $callback
     */
    public function filterParameters(Closure $callback): static;
}
