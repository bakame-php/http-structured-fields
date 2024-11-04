<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Bakame\Http\StructuredFields\Validation\ErrorCode;
use Bakame\Http\StructuredFields\Validation\ParsedItem;
use Bakame\Http\StructuredFields\Validation\Result;
use Bakame\Http\StructuredFields\Validation\Violation;
use Bakame\Http\StructuredFields\Validation\ViolationList;
use Stringable;

/**
 * Structured field Item validator.
 *
 * @phpstan-import-type SfItemInput from StructuredField
 * @phpstan-import-type SfType from StructuredField
 *
 * @phpstan-type SfParameterKeyRule array{validate?:callable(SfType): (bool|string), required?:bool|string, default?:SfType|null}
 * @phpstan-type SfParameterIndexRule array{validate?:callable(SfType, string): (bool|string), required?:bool|string, default?:array{0:string, 1:SfType}|array{}}
 */
final class ItemValidator
{
    public const USE_KEYS = 1;
    public const USE_INDICES = 2;

    /** @var callable(SfType): (string|bool) */
    private mixed $valueConstraint;
    /** @var ?callable(Parameters): (string|bool) */
    private mixed $parametersConstraint;
    private int $parametersType;
    /** @var array<string, SfParameterKeyRule>|array<int, SfParameterIndexRule> */
    private array $parametersMembersConstraints;

    /**
     * @param callable(SfType): (string|bool) $valueConstraint
     * @param ?callable(Parameters): (string|bool) $parametersConstraint
     * @param array<string, SfParameterKeyRule>|array<int, SfParameterIndexRule> $parametersMembersConstraints
     */
    private function __construct(
        callable $valueConstraint,
        ?callable $parametersConstraint = null,
        int $parametersType = self::USE_KEYS,
        array $parametersMembersConstraints = [],
    ) {
        $this->valueConstraint = $valueConstraint;
        $this->parametersConstraint = $parametersConstraint;
        $this->parametersType = $parametersType;
        $this->parametersMembersConstraints = $parametersMembersConstraints;
    }

    public static function new(): self
    {
        return new self(fn (mixed $value) => false);
    }

    /**
     * Validates the Item value.
     *
     * On success populate the result item property
     * On failure populates the result errors property
     *
     * @param callable(SfType): (string|bool) $constraint
     */
    public function value(callable $constraint): self
    {
        return new self(
            $constraint,
            $this->parametersConstraint,
            $this->parametersType,
            $this->parametersMembersConstraints,
        );
    }

    /**
     * Validates the Item parameters as a whole.
     *
     * On failure populates the result errors property
     *
     * @param ?callable(Parameters): (string|bool) $constraint
     */
    public function parameters(?callable $constraint, int $type = self::USE_KEYS): self
    {
        return new self(
            $this->valueConstraint,
            $constraint,
            [] === $this->parametersMembersConstraints ? $type : $this->parametersType,
            $this->parametersMembersConstraints,
        );
    }

    /**
     * Validate each parameters value per key.
     *
     * On success populate the result item property
     * On failure populates the result errors property
     *
     * @param array<string, SfParameterKeyRule> $constraints
     */
    public function parametersByKeys(array $constraints): self
    {
        return new self(
            $this->valueConstraint,
            $this->parametersConstraint,
            self::USE_KEYS,
            $constraints,
        );
    }

    /**
     * Validate each parameters value per indices.
     *
     * On success populate the result item property
     * On failure populates the result errors property
     *
     * @param array<int, SfParameterIndexRule> $constraints
     */
    public function parametersByIndices(array $constraints): self
    {
        return new self(
            $this->valueConstraint,
            $this->parametersConstraint,
            self::USE_INDICES,
            $constraints,
        );
    }

    /**
     * Validates the structured field Item.
     */
    public function validate(Item|Stringable|string $item): ParsedItem
    {
        $violations = new ViolationList();
        $itemValue = null;
        if (!$item instanceof Item) {
            try {
                $item = Item::fromHttpValue($item);
            } catch (SyntaxError $exception) {
                $violations->add(ErrorCode::FailedItemParsing, new Violation('The item string could not be parsed.', previous: $exception));

                return new ParsedItem($itemValue, [], $violations);
            }
        }

        if (null !== $this->valueConstraint) {
            try {
                $itemValue = $item->value($this->valueConstraint);
            } catch (Violation $exception) {
                $violations->add(ErrorCode::InvalidItemValue, $exception);
            }
        }

        $parsedParameters = null;
        if ([] !== $this->parametersMembersConstraints) {
            $parsedParameters = match ($this->parametersType) {
                self::USE_INDICES => $item->parameters()->validateByIndices($this->parametersMembersConstraints), /* @phpstan-ignore-line */
                default => $item->parameters()->validateByKeys($this->parametersMembersConstraints), /* @phpstan-ignore-line */
            };

            $violations->addAll($parsedParameters->errors);
        }

        $errorMessage = $this->applyParametersConstraint($item->parameters());
        if (!is_bool($errorMessage)) {
            $violations->add(ErrorCode::InvalidParametersValues, new Violation($errorMessage));
        }

        $parsedParameters = $parsedParameters?->parameters ?? [];
        if ([] === $this->parametersMembersConstraints && true === $errorMessage) {
            $parsedParameters = match ($this->parametersType) {
                self::USE_KEYS => array_map(fn (Item $item) => $item->value(), [...$item->parameters()]), /* @phpstan-ignore-line */
                default => array_map(fn (array $pair) => [$pair[0], $pair[1]->value()], [...$item->parameters()->toPairs()]),
            };
        }

        return new ParsedItem($itemValue, $parsedParameters, $violations);
    }

    private function applyParametersConstraint(Parameters $parameters): bool|string
    {
        if (null === $this->parametersConstraint) {
            return true;
        }

        $errorMessage = ($this->parametersConstraint)($parameters);
        if (true === $errorMessage) {
            return true;
        }

        if (!is_string($errorMessage) || '' === trim($errorMessage)) {
            $errorMessage = 'The parameters constraints are not met.';
        }

        return $errorMessage;
    }
}