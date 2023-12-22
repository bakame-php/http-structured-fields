<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

if (!function_exists('parse')) {
    /**
     * Parse a header conform to the HTTP Structured Field RFCs.
     *
     * @param 'dictionary'|'list'|'item' $type
     *
     */
    function parse(string $httpValue, string $type): StructuredField
    {
        return DataType::from($type)->newStructuredField($httpValue);
    }
}

if (!function_exists('build')) {
    /**
     * Build an HTTP header value from a HTTP Structured Field instance.
     */
    function build(StructuredField $structuredField): string
    {
        return $structuredField->toHttpValue();
    }
}
