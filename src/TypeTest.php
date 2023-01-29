<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Stringable;

final class TypeTest extends TestCase
{
    /** @test */
    public function it_will_throw_if_the_type_is_no_supported(): void
    {
        $this->expectException(SyntaxError::class);

        Type::fromValue([]);
    }

    /**
     * @dataProvider itemTypeProvider
     * @test
     */
    public function it_can_tell_the_item_type(mixed $value, Type $expectedType): void
    {
        self::assertSame($expectedType, Type::fromValue($value));
    }

    /**
     * @return iterable<string, array{value:mixed, expectedType:Type}>
     */
    public function itemTypeProvider(): iterable
    {
        return [
            'boolean' => [
                'value' => false,
                'expectedType' => Type::Boolean,
            ],
            'integer' => [
                'value' => 42,
                'expectedType' => Type::Integer,
            ],
            'decimal' => [
                'value' => 42.0,
                'expectedType' => Type::Decimal,
            ],
            'string' => [
                'value' => '42',
                'expectedType' => Type::String,
            ],
            'token' => [
                'value' => Token::fromString('forty-two'),
                'expectedType' => Type::Token,
            ],
            'byte' => [
                'value' => ByteSequence::fromDecoded('😊'),
                'expectedType' => Type::ByteSequence,
            ],
            'stringable object' => [
                'value' => new class() implements Stringable {
                    public function __toString(): string
                    {
                        return '42';
                    }
                },
                'expectedType' => Type::String,
            ],
            'date' => [
                'value' => new DateTime('2020-07-12 13:37:00'),
                'expectedType' => Type::Date,
            ],
            'another date' => [
                'value' => new DateTimeImmutable('2020-07-12 13:37:00'),
                'expectedType' => Type::Date,
            ],
        ];
    }
}
