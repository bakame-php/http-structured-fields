<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayObject;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stringable;

final class ItemTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $paths = [
        '/boolean.json',
        '/number.json',
        '/number-generated.json',
        '/string.json',
        '/string-generated.json',
        '/token.json',
        '/token-generated.json',
        '/item.json',
        '/date.json',
    ];

    #[Test]
    public function it_fails_to_instantiate_a_decimal_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(1_000_000_000_000.0);
    }

    #[Test]
    public function it_fails_to_instantiate_a_decimal_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(-1_000_000_000_000.0);
    }

    #[Test]
    public function it_instantiate_a_decimal(): void
    {
        self::assertSame('42.0', Item::from(42.0)->toHttpValue());
        self::assertSame('42.0', (string) Item::from(42.0));
    }

    #[Test]
    public function it_fails_to_instantiate_a_integer_too_big(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(1_000_000_000_000_000);
    }

    #[Test]
    public function it_fails_to_instantiate_a_integer_too_small(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(-1_000_000_000_000_000);
    }

    #[Test]
    public function it_instantiates_an_integer(): void
    {
        self::assertSame('42', Item::from(42)->toHttpValue());
    }

    #[Test]
    public function it_instantiates_a_boolean(): void
    {
        self::assertSame('?1', Item::from(true)->toHttpValue());
        self::assertSame('?0', Item::from(false)->toHttpValue());
    }

    #[Test]
    public function it_instantiates_a_token(): void
    {
        self::assertSame('helloworld', Item::from(Token::fromString('helloworld'))->toHttpValue());
        self::assertSame('helloworld', Item::fromToken('helloworld')->toHttpValue());
    }

    #[Test]
    public function it_instantiates_a_date(): void
    {
        $item = Item::fromHttpValue('@1583349795');

        self::assertEquals($item, Item::from(new DateTimeImmutable('2020-03-04 19:23:15')));
        self::assertEquals($item, Item::from(new DateTime('2020-03-04 19:23:15')));
        self::assertSame('@1583349795', $item->toHttpValue());
    }

    #[Test]
    public function it_fails_to_instantiate_an_invalid_date_format(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromHttpValue('@112345.678');
    }

    #[Test]
    public function it_fails_to_instantiate_an_out_of_range_date_in_the_future(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(new DateTime('@'. 1_000_000_000_000_000));
    }

    #[Test]
    public function it_fails_to_instantiate_an_out_of_range_date_in_the_past(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from(new DateTime('@'.-1_000_000_000_000_000));
    }

    #[Test]
    public function it_instantiates_a_binary(): void
    {
        $byteSequence = ByteSequence::fromDecoded('foobar');

        self::assertEquals($byteSequence, Item::from(ByteSequence::fromDecoded('foobar'))->value());
        self::assertEquals($byteSequence, Item::fromDecodedByteSequence('foobar')->value());
        self::assertEquals($byteSequence, Item::fromEncodedByteSequence('Zm9vYmFy')->value());
    }

    #[Test]
    public function it_instantiates_a_string(): void
    {
        self::assertSame('"foobar"', Item::from('foobar')->toHttpValue());
    }

    #[Test]
    public function it_fails_to_instantiate_an_invalid_string(): void
    {
        $this->expectException(SyntaxError::class);

        Item::from("\0foobar");
    }

    #[Test]
    #[DataProvider('itemTypeProvider')]
    public function it_can_tell_the_item_type(Item $item, Type $expectedType): void
    {
        self::assertSame($expectedType, $item->type());
    }

    /**
     * @return iterable<string, array{item:Item, expectedType:Type}>
     */
    public static function itemTypeProvider(): iterable
    {
        return [
            'boolean' => [
                'item' => Item::from(false),
                'expectedType' => Type::Boolean,
            ],
            'integer' => [
                'item' => Item::from(42),
                'expectedType' => Type::Integer,
            ],
            'decimal' => [
                'item' => Item::from(42.0),
                'expectedType' => Type::Decimal,
            ],
            'string' => [
                'item' => Item::from('42'),
                'expectedType' => Type::String,
            ],
            'token' => [
                'item' => Item::from(Token::fromString('forty-two')),
                'expectedType' => Type::Token,
            ],
            'byte' => [
                'item' => Item::from(ByteSequence::fromDecoded('😊')),
                'expectedType' => Type::ByteSequence,
            ],
            'stringable object' => [
                'item' => Item::from(new class() implements Stringable {
                    public function __toString(): string
                    {
                        return '42';
                    }
                }),
                'expectedType' => Type::String,
            ],
            'date' => [
                'item' => Item::from(new DateTimeImmutable('2020-07-12 13:37:00')),
                'expectedType' => Type::Date,
            ],
        ];
    }

    #[Test]
    public function test_in_can_be_instantiated_using_bare_items(): void
    {
        $item1 = Item::from('/terms', [
            'string' => '42',
            'integer' => 42,
            'float' => 4.2,
            'boolean' => true,
            'token' => Token::fromString('forty-two'),
            'byte-sequence' => ByteSequence::fromDecoded('a42'),
            'date' => new DateTimeImmutable('2020-12-01 11:43:17'),
        ]);

        $item2 = Item::from('/terms', new ArrayObject([
            'string' => Item::from('42'),
            'integer' => Item::from(42),
            'float' => Item::from(4.2),
            'boolean' => Item::from(true),
            'token' => Item::from(Token::fromString('forty-two')),
            'byte-sequence' => Item::from(ByteSequence::fromDecoded('a42')),
            'date' => Item::from(new DateTimeImmutable('2020-12-01 11:43:17')),
        ]));

        self::assertEquals($item2, $item1);
    }

    #[Test]
    public function it_will_fail_with_wrong_token(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromHttpValue('foo,bar;a=3');
    }

    #[Test]
    public function it_can_access_its_parameter_values(): void
    {
        $instance = Item::fromHttpValue('1; a; b=?0');

        self::assertTrue($instance->parameters()->get('a')->value());
    }

    #[Test]
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $this->expectException(StructuredFieldError::class);

        Item::fromHttpValue('1; a; b=?0')->parameters()->get('bar')->value();
    }

    #[Test]
    public function it_can_create_an_item_from_a_array_of_pairs(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'));
        $instance2 = Item::fromPair([Token::fromString('babayaga')]);
        $instance3 = Item::fromPair([Token::fromString('babayaga'), []]);

        self::assertEquals($instance2, $instance1);
        self::assertEquals($instance3, $instance1);
    }

    /**
     * @param array<mixed> $pair
     */
    #[Test]
    #[DataProvider('invalidPairProvider')]
    public function it_fails_to_create_an_item_from_an_array_of_pairs(array $pair): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromPair($pair);  /* @phpstan-ignore-line */
    }

    /**
     * @return iterable<string, array{pair:array<mixed>}>
     */
    public static function invalidPairProvider(): iterable
    {
        yield 'empty pair' => ['pair' => []];
        yield 'empty extra filled pair' => ['pair' => [1, [2], 3]];
        yield 'associative array' => ['pair' => ['value' => 'bar', 'parameters' => ['foo' => 'bar']]];
    }

    #[Test]
    public function it_can_create_an_item_from_a_array_of_pairs_and_parameters(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = Item::fromPair([Token::fromString('babayaga'), [['a', true]]]);

        self::assertEquals($instance2, $instance1);
    }

    #[Test]
    public function it_can_create_via_with_value_method_a_new_object(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = $instance1->withValue(Token::fromString('babayaga'));
        $instance3 = $instance1->withValue(new class() implements Stringable {
            public function __toString(): string
            {
                return 'babayaga';
            }
        });

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1, $instance3);
        self::assertNotSame($instance1->parameters(), $instance3->parameters());
        self::assertEquals($instance1->parameters(), $instance3->parameters());
    }

    #[Test]
    public function it_can_create_via_with_parameters_method_a_new_object(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = $instance1->withParameters(Parameters::fromAssociative(['a' => true]));
        $instance3 = $instance1->withParameters(Parameters::fromAssociative(['a' => false]));

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1, $instance3);
        self::assertEquals($instance1->value(), $instance3->value());
    }

    #[Test]
    public function it_can_create_via_parameters_access_methods_a_new_object(): void
    {
        $instance1 = Item::from(Token::fromString('babayaga'), ['a' => true]);
        $instance7 = $instance1->addParameter('a', true);
        $instance2 = $instance1->appendParameter('a', true);
        $instance3 = $instance1->prependParameter('a', false);
        $instance4 = $instance1->withoutParameter('b');
        $instance5 = $instance1->withoutParameter('a');
        $instance6 = $instance1->withoutAllParameters();

        self::assertSame($instance1, $instance2);
        self::assertSame($instance1, $instance7);
        self::assertNotSame($instance1, $instance3);
        self::assertEquals($instance1->value(), $instance3->value());
        self::assertSame($instance1, $instance4);
        self::assertTrue($instance5->parameters()->hasNoMembers());
        self::assertTrue($instance6->parameters()->hasNoMembers());
    }
}
