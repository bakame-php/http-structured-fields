<?php

declare(strict_types=1);

use Bakame\Http\StructuredFields\Dictionary;
use Bakame\Http\StructuredFields\InnerList;
use Bakame\Http\StructuredFields\Item;
use Bakame\Http\StructuredFields\OuterList;
use Bakame\Http\StructuredFields\Parameters;
use Bakame\Http\StructuredFields\StructuredField;

if (!function_exists('http_parse_structured_field')) {
    /**
     * Parse a header conform to the HTTP Structured Field RFCs.
     *
     * @param 'dictionary'|'parameters'|'list'|'innerlist'|'item' $type
     * @throws OutOfRangeException If the value is unknown or undefined
     */
    function http_parse_structured_field(string $type, string $httpValue): StructuredField
    {
        return match ($type) {
            'dictionary' => Dictionary::fromHttpValue($httpValue),
            'parameters' => Parameters::fromHttpValue($httpValue),
            'list' => OuterList::fromHttpValue($httpValue),
            'innerlist' => InnerList::fromHttpValue($httpValue),
            'item' => Item::fromHttpValue($httpValue),  /* @phpstan-ignore-line */
            default => throw new ValueError('The submitted type "'.$type.'" is unknown or not supported,'),  /* @phpstan-ignore-line */
        };
    }
}
