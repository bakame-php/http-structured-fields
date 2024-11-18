<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\StructuredFieldProvider;
use Bakame\Http\StructuredFields\SyntaxError;
use Stringable;

/**
 * Structured field Item validator.
 *
 * @phpstan-import-type SfType from StructuredFieldProvider
 */
final class ItemValidator
{
    /** @var callable(SfType): (string|bool) */
    private mixed $valueConstraint;
    private ParametersValidator $parametersConstraint;

    /**
     * @param callable(SfType): (string|bool) $valueConstraint
     */
    private function __construct(
        callable $valueConstraint,
        ParametersValidator $parametersConstraint,
    ) {
        $this->valueConstraint = $valueConstraint;
        $this->parametersConstraint = $parametersConstraint;
    }

    public static function new(): self
    {
        return new self(fn (mixed $value) => false, ParametersValidator::new());
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
        return new self($constraint, $this->parametersConstraint);
    }

    /**
     * Validates the Item parameters as a whole.
     *
     * On failure populates the result errors property
     */
    public function parameters(ParametersValidator $constraint): self
    {
        return new self($this->valueConstraint, $constraint);
    }

    public function __invoke(Item|Stringable|string $item): bool|string
    {
        $result = $this->validate($item);

        return $result->isSuccess() ? true : (string) $result->errors;
    }

    /**
     * Validates the structured field Item.
     */
    public function validate(Item|Stringable|string $item): Result
    {
        $violations = new ViolationList();
        if (!$item instanceof Item) {
            try {
                $item = Item::fromHttpValue($item);
            } catch (SyntaxError $exception) {
                $violations->add(ErrorCode::ItemFailedParsing->value, new Violation('The item string could not be parsed.', previous: $exception));

                return Result::failed($violations);
            }
        }

        try {
            $itemValue = $item->value($this->valueConstraint);
        } catch (Violation $exception) {
            $itemValue = null;
            $violations->add(ErrorCode::ItemValueFailedValidation->value, $exception);
        }

        $validate = $this->parametersConstraint->validate($item->parameters());
        $violations->addAll($validate->errors);
        if ($violations->isNotEmpty()) {
            return Result::failed($violations);
        }

        /** @var ValidatedParameters $validatedParameters */
        $validatedParameters = $validate->data;

        return Result::success(new ValidatedItem($itemValue, $validatedParameters));
    }
}
