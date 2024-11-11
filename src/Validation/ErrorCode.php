<?php

namespace Bakame\Http\StructuredFields\Validation;

/**
 * General Error Code-.
 *
 * When adding new codes the name MUST be prefixed with
 * a `@` to avoid conflicting with parameters keys.
 */
enum ErrorCode: string
{
    case ItemFailedParsing = '@item.failed.parsing';
    case ItemValueFailedValidation = '@item.value.failed.validation';
    case ParametersFailedParsing = '@parameters.failed.parsing';
    case ParametersMissingConstraints = '@parameters.missing.constraints';
    case ParametersFailedCriteria = '@parameters.failed.criteria';

    /**
     * @return array<string>
     */
    public static function list(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
