<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\Parameters;
use Bakame\Http\StructuredFields\StructuredField;
use Bakame\Http\StructuredFields\SyntaxError;
use Stringable;

/**
 * Structured field Item validator.
 *
 * @phpstan-import-type SfType from StructuredField
 *
 * @phpstan-type SfParameterKeyRule array{validate?:callable(SfType): (bool|string), required?:bool|string, default?:SfType|null}
 * @phpstan-type SfParameterIndexRule array{validate?:callable(SfType, string): (bool|string), required?:bool|string, default?:array{0:string, 1:SfType}|array{}}
 */
final class ParametersValidator
{
    public const USE_KEYS = 1;
    public const USE_INDICES = 2;

    /** @var ?callable(Parameters): (string|bool) */
    private mixed $criteria;
    private int $type;
    /** @var array<string, SfParameterKeyRule>|array<int, SfParameterIndexRule> */
    private array $filterConstraints;

    /**
     * @param ?callable(Parameters): (string|bool) $criteria
     * @param array<string, SfParameterKeyRule>|array<int, SfParameterIndexRule> $filterConstraints
     */
    private function __construct(
        ?callable $criteria = null,
        int $type = self::USE_KEYS,
        array $filterConstraints = [],
    ) {
        $this->criteria = $criteria;
        $this->type = $type;
        $this->filterConstraints = $filterConstraints;
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Validates the Item parameters as a whole.
     *
     * On failure populates the result errors property
     *
     * @param ?callable(Parameters): (string|bool) $criteria
     */
    public function filterByCriteria(?callable $criteria, int $type = self::USE_KEYS): self
    {
        return new self($criteria, [] === $this->filterConstraints ? $type : $this->type, $this->filterConstraints);
    }

    /**
     * Validate each parameters value per key.
     *
     * On success populate the result item property
     * On failure populates the result errors property
     *
     * @param array<string, SfParameterKeyRule> $constraints
     */
    public function filterByKeys(array $constraints): self
    {
        return new self($this->criteria, self::USE_KEYS, $constraints);
    }

    /**
     * Validate each parameters value per indices.
     *
     * On success populate the result item property
     * On failure populates the result errors property
     *
     * @param array<int, SfParameterIndexRule> $constraints
     */
    public function filterByIndices(array $constraints): self
    {
        return new self($this->criteria, self::USE_INDICES, $constraints);
    }

    public function __invoke(Parameters|Stringable|string $parameters): bool|string
    {
        $result = $this->validate($parameters);

        return $result->isSuccess() ? true : (string) $result->errors;
    }

    /**
     * Validates the structured field Item.
     *
     * @return Result<ProcessedParameters>|Result<null>
     */
    public function validate(Parameters|Stringable|string $parameters): Result
    {
        $violations = new ViolationList();
        if (!$parameters instanceof Parameters) {
            try {
                $parameters = Parameters::fromHttpValue($parameters);
            } catch (SyntaxError $exception) {
                $violations->add(ErrorCode::ParametersFailedParsing->value, new Violation('The parameters string could not be parsed.', previous: $exception));

                return Result::failed($violations);
            }
        }

        if ([] == $this->filterConstraints && null === $this->criteria) {
            $violations->add(ErrorCode::ParametersMissingConstraints->value, new Violation('The parameters constraints are missing.'));
        }

        $parsedParameters = new ProcessedParameters();
        if ([] !== $this->filterConstraints) {
            $parsedParameters = match ($this->type) {
                self::USE_INDICES => $this->validateByIndices($parameters),
                default => $this->validateByKeys($parameters),
            };

            if ($parsedParameters->isFailed()) {
                $violations->addAll($parsedParameters->errors);
            } else {
                $parsedParameters = $parsedParameters->data;
            }
        }

        $errorMessage = $this->validateByCriteria($parameters);
        if (!is_bool($errorMessage)) {
            $violations->add(ErrorCode::ParametersFailedCriteria->value, new Violation($errorMessage));
        }

        /** @var ProcessedParameters $parsedParameters */
        $parsedParameters = $parsedParameters ?? new ProcessedParameters();
        if ([] === $this->filterConstraints && true === $errorMessage) {
            $parsedParameters = new ProcessedParameters(match ($this->type) {
                self::USE_KEYS => array_map(fn (Item $item) => $item->value(), [...$parameters->toAssociative()]),
                default => array_map(fn (array $pair) => [$pair[0], $pair[1]->value()], [...$parameters]),
            });
        }

        return match ($violations->isNotEmpty()) {
            true => Result::failed($violations),
            default => Result::success($parsedParameters),
        };
    }

    private function validateByCriteria(Parameters $parameters): bool|string
    {
        if (null === $this->criteria) {
            return true;
        }

        $errorMessage = ($this->criteria)($parameters);
        if (true === $errorMessage) {
            return true;
        }

        if (!is_string($errorMessage) || '' === trim($errorMessage)) {
            $errorMessage = 'The parameters constraints are not met.';
        }

        return $errorMessage;
    }

    /**
     * Validate the current parameter object using its keys and return the parsed values and the errors.
     *
     * @return Result<ProcessedParameters>|Result<null>
     */
    private function validateByKeys(Parameters $parameters): Result /* @phpstan-ignore-line */
    {
        $data = [];
        $violations = new ViolationList();
        /**
         * @var string $key
         * @var SfParameterKeyRule $rule
         */
        foreach ($this->filterConstraints as $key => $rule) {
            try {
                $data[$key] = $parameters->valueByKey($key, $rule['validate'] ?? null, $rule['required'] ?? false, $rule['default'] ?? null);
            } catch (Violation $exception) {
                $violations[$key] = $exception;
            }
        }

        return match ($violations->isNotEmpty()) {
            true => Result::failed($violations),
            default => Result::success(new ProcessedParameters($data)),
        };
    }

    /**
     * Validate the current parameter object using its indices and return the parsed values and the errors.
     *
     * @return Result<ProcessedParameters>|Result<null>
     */
    public function validateByIndices(Parameters $parameters): Result
    {
        $data = [];
        $violations = new ViolationList();
        /**
         * @var int $index
         * @var SfParameterIndexRule $rule
         */
        foreach ($this->filterConstraints as $index => $rule) {
            try {
                $data[$index] = $parameters->valueByIndex($index, $rule['validate'] ?? null, $rule['required'] ?? false, $rule['default'] ?? []);
            } catch (Violation $exception) {
                $violations[$index] = $exception;
            }
        }

        return match ($violations->isNotEmpty()) {
            true => Result::failed($violations),
            default => Result::success(new ProcessedParameters($data)),
        };
    }
}
