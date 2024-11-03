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
}
