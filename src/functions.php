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
     *
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
            default => throw new OutOfBoundsException('The submitted type "'.$type.'" is unknown or not supported,'),  /* @phpstan-ignore-line */
        };
    }
}

if (!function_exists('http_build_structured_field')) {
    /**
     * Build an HTTP Structured Field Text representation fron an iterable PHP structure.
     *
     * @param 'dictionary'|'parameters'|'list'|'innerlist'|'item' $type
     * @param iterable $data the iterable data used to generate the structured field
     *
     * @throws OutOfBoundsException If the type is unknown or unsupported
     *
     * @see Dictionary::fromPairs()
     * @see Parameters::fromPairs()
     * @see OuterList::fromPairs()
     * @see InnerList::fromPair()
     * @see Item::fromPair()
     */
    function http_build_structured_field(string $type, iterable $data): string /* @phptan-ignore-line */
    {
        return match ($type) {
            'dictionary' => Dictionary::fromPairs($data)->toHttpValue(),
            'parameters' => Parameters::fromPairs($data)->toHttpValue(),
            'list' => OuterList::fromPairs($data)->toHttpValue(),
            'innerlist' => InnerList::fromPair([...$data])->toHttpValue(), /* @phpstan-ignore-line */
            'item' => Item::fromPair([...$data])->toHttpValue(), /* @phpstan-ignore-line */
            default => throw new OutOfBoundsException('The submitted type "'.$type.'" is unknown or not supported,'),  /* @phpstan-ignore-line */
        };
    }
}
