<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;

final class TypeTest extends TestCase
{
    #[Test]
    public function it_will_throw_if_the_type_is_no_supported(): void
    {
        $this->expectException(SyntaxError::class);

        Type::fromValue([]);
    }

    #[Test]
    public function it_will_return_null_if_the_type_is_no_supported(): void
    {
        self::assertNull(Type::tryFromValue([]));
    }

    #[Test]
    public function it_will_return_null_if_the_type_is_invalid(): void
    {
        self::assertNull(Type::tryFromValue(1_000_000_000_000_000));
    }

    #[Test]
    public function it_can_tell_the_item_type_from_a_value_instance(): void
    {
        self::assertFalse(Type::Integer->equals(Item::from(42.0)));
        self::assertTrue(Type::Token->equals(Item::fromToken('text/csv')));
    }

    #[Test]
    #[DataProvider('itemTypeProvider')]
    public function it_can_tell_the_item_type(mixed $value, Type $expectedType): void
    {
        self::assertTrue($expectedType->equals(Type::fromValue($value)));
        self::assertTrue($expectedType->equals(Type::tryFromValue($value)));
    }

    /**
     * @return iterable<string, array{value:mixed, expectedType:Type}>
     */
    public static function itemTypeProvider(): iterable
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
            'datetime implementing object' => [
                'value' => new DateTime('2020-07-12 13:37:00'),
                'expectedType' => Type::Date,
            ],
            'another datetime implementing object' => [
                'value' => new DateTimeImmutable('2020-07-12 13:37:00'),
                'expectedType' => Type::Date,
            ],
            'an item object' => [
                'value' => Item::from(new DateTimeImmutable('2020-07-12 13:37:00')),
                'expectedType' => Type::Date,
            ],
        ];
    }
}
