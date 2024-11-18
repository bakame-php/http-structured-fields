<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

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

        $parsedParameters = new ValidatedParameters();
        if ([] !== $this->filterConstraints) {
            $parsedParameters = match ($this->type) {
                self::USE_INDICES => $this->validateByIndices($parameters),
                default => $this->validateByNames($parameters),
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

        /** @var ValidatedParameters $parsedParameters */
        $parsedParameters = $parsedParameters ?? new ValidatedParameters();
        if ([] === $this->filterConstraints && true === $errorMessage) {
            $parsedParameters = new ValidatedParameters(match ($this->type) {
                self::USE_KEYS => $this->toAssociative($parameters),
                default => $this->toList($parameters),
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
     * @return Result<ValidatedParameters>|Result<null>
     */
    private function validateByNames(Parameters $parameters): Result /* @phpstan-ignore-line */
    {
        $data = [];
        $violations = new ViolationList();
        /**
         * @var string $name
         * @var SfParameterKeyRule $rule
         */
        foreach ($this->filterConstraints as $name => $rule) {
            try {
                $data[$name] = $parameters->valueByName($name, $rule['validate'] ?? null, $rule['required'] ?? false, $rule['default'] ?? null);
            } catch (Violation $exception) {
                $violations[$name] = $exception;
            }
        }

        return match ($violations->isNotEmpty()) {
            true => Result::failed($violations),
            default => Result::success(new ValidatedParameters($data)),
        };
    }

    /**
     * Validate the current parameter object using its indices and return the parsed values and the errors.
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
            default => Result::success(new ValidatedParameters($data)),
        };
    }

    /**
     * @return array<string,SfType>
     */
    private function toAssociative(Parameters $parameters): array
    {
        $assoc = [];
        foreach ($parameters as $parameter) {
            $assoc[$parameter[0]] = $parameter[1]->value();
        }

        return $assoc;
    }

    /**
     * @return array<int, array{0:string, 1:SfType}>
     */
    private function toList(Parameters $parameters): array
    {
        $list = [];
        foreach ($parameters as $index => $parameter) {
            $list[$index] = [$parameter[0], $parameter[1]->value()];
        }

        return $list;
    }
}
