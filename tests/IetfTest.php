<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplObjectStorage;

final class IetfTest extends TestCase
{
    #[Test]
    #[DataProvider('variableRfc8941Provider')]
    public function it_will_tell_if_rfc8941_can_supports_a_value(mixed $value, bool $expectedRfc8941, bool $expectedRfc9651): void
    {
        self::assertSame($expectedRfc8941, Ietf::Rfc8941->supports($value));
        self::assertSame($expectedRfc9651, Ietf::Rfc9651->supports($value));
    }

    /**
     * @return Iterator<string, array{value:mixed, expectedRfc8941: bool, expectedRfc9651: bool}>
     */
    public static function variableRfc8941Provider(): Iterator
    {
        yield 'integer' => [
            'value' => 1,
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield 'float' => [
            'value' => 42.0,
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield 'string' => [
            'value' => 'foobar bar baz',
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield 'token' => [
            'value' => 'application/xml',
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield 'datetime' => [
            'value' => new DateTimeImmutable('@1234567879'),
            'expectedRfc8941' => false,
            'expectedRfc9651' => true,
        ];

        yield 'bytesequence' => [
            'value' => 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==',
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield 'displaystring' => [
            'value' => 'bébé',
            'expectedRfc8941' => false,
            'expectedRfc9651' => true,
        ];

        yield 'structuredField not supported' => [
            'value' => Item::fromTimestamp(1234567879),
            'expectedRfc8941' => false,
            'expectedRfc9651' => true,
        ];

        yield 'structuredField supported' => [
            'value' => Item::fromInteger(123456789),
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield 'structuredFieldProvider supported' => [
            'value' => new class () implements StructuredFieldProvider {
                public function toStructuredField(): StructuredField
                {
                    return Item::fromInteger(123456789);
                }
            },
            'expectedRfc8941' => true,
            'expectedRfc9651' => true,
        ];

        yield  'unknown type' => [
            'value' => new SplObjectStorage(),
            'expectedRfc8941' => false,
            'expectedRfc9651' => false,
        ];
    }
}
