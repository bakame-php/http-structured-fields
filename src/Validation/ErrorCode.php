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
    case FailedItemParsing = '@failed.item.parsing';
    case InvalidItemValue = '@invalid.item.value';
    case InvalidParametersValues = '@invalid.parameters.values';
    case MissingParameterConstraints = '@missing.parameters.constraints';

    /**
     * @return array<string>
     */
    public static function list(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
