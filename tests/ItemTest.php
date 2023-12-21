<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayObject;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stringable;

/**
 * @phpstan-import-type SfItemInput from StructuredField
 */
final class ItemTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'boolean.json',
        'number.json',
        'number-generated.json',
        'string.json',
        'string-generated.json',
        'token.json',
        'token-generated.json',
        'item.json',
        'date.json',
    ];

    #[Test]
    #[DataProvider('provideInvalidArguments')]
    public function it_fails_to_instantiate_an_item(ByteSequence|Token|DateTimeInterface|string|int|float|bool $value): void
    {
        $this->expectException(SyntaxError::class);

        Item::new($value);
    }

    /**
     * @return iterable<string, array{value:mixed}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'if the decimal is too big' => [
            'value' => 1_000_000_000_000.0,
        ];

        yield 'if the decimal is too small' => [
            'value' => -1_000_000_000_000.0,
        ];

        yield 'if the integer is too big' => [
            'value' => 1_000_000_000_000_000,
        ];

        yield 'if the integer is too small' => [
            'value' => -1_000_000_000_000_000,
        ];

        yield 'if the date is too much in the future' => [
            'value' => new DateTime('@'. 1_000_000_000_000_000),
        ];

        yield 'if the date is too much in the past' => [
            'value' => new DateTime('@'.-1_000_000_000_000_000),
        ];

        yield 'if the string contains invalud characters' => [
            'value' => "\0foobar",
        ];
    }

    #[Test]
    #[DataProvider('provideFrom1stArgument')]
    public function it_instantiate_many_types(ByteSequence|Token|DateTimeInterface|string|int|float|bool $value, string $expected): void
    {
        self::assertSame($expected, Item::new($value)->toHttpValue());
    }

    #[Test]
    #[DataProvider('provideFrom1stArgument')]
    public function it_updates_item(ByteSequence|Token|DateTimeInterface|string|int|float|bool $value, string $expected): void
    {
        $parameters = Parameters::fromAssociative(['foo' => 'bar']);

        self::assertSame(
            $expected.$parameters->toHttpValue(),
            (string) Item::fromAssociative('hello-world', $parameters)->withValue($value)
        );
    }

    /**
     * @return iterable<string, array{value:SfItemInput, expected:string}>>
     */
    public static function provideFrom1stArgument(): iterable
    {
        return [
            'decimal' => ['value' => 42.0, 'expected' => '42.0'],
            'string' => ['value' => 'forty-two', 'expected' => '"forty-two"'],
            'integer' => ['value' => 42,   'expected' => '42'],
            'boolean true' => ['value' => true, 'expected' => '?1'],
            'boolean false' => ['value' => false, 'expected' => '?0'],
            'token' => ['value' => Token::fromString('helloworld'), 'expected' => 'helloworld'],
            'byte sequence' => ['value' => ByteSequence::fromDecoded('foobar'), 'expected' => ':Zm9vYmFy:'],
            'datetime' => ['value' => new DateTime('2020-03-04 19:23:15'), 'expected' => '@1583349795'],
        ];
    }

    #[Test]
    public function it_instantiates_a_token(): void
    {
        self::assertSame('helloworld', Item::fromToken('helloworld')->toHttpValue());
    }

    #[Test]
    public function it_instantiates_a_stringable_object_from_string(): void
    {
        $object = new class () implements Stringable {
            public function __toString(): string
            {
                return 'forty-two';
            }
        };

        self::assertSame('"forty-two"', Item::fromString($object)->toHttpValue());
    }

    #[Test]
    public function it_instantiates_a_date(): void
    {
        $item = Item::fromHttpValue('@1583349795');
        self::assertEquals($item, Item::new(new DateTimeImmutable('2020-03-04 19:23:15')));
        self::assertEquals($item, Item::new(new DateTime('2020-03-04 19:23:15')));
        self::assertEquals($item, Item::fromDate(new DateTime('2020-03-04 19:23:15')));
        self::assertEquals($item, Item::fromTimestamp(1583349795));
        self::assertEquals($item, Item::fromDateFormat(DateTimeInterface::RFC822, 'Wed, 04 Mar 20 19:23:15 +0000'));
        self::assertTrue($item ->value() < Item::fromDateString('-1 year')->value());
        self::assertSame('@1583349795', $item->toHttpValue());
    }

    #[Test]
    public function it_fails_to_instantiate_an_invalid_date_format(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromHttpValue('@112345.678');
    }

    #[Test]
    public function it_fails_to_instantiate_an_out_of_range_date_object_in_the_future(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDate((new DateTimeImmutable())->setTimestamp(1_000_000_000_000_000));
    }

    #[Test]
    public function it_fails_to_instantiate_an_out_of_range_date_object_in_the_past(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDate((new DateTime())->setTimestamp(-1_000_000_000_000_000));
    }

    #[Test]
    public function it_fails_to_instantiate_an_out_of_range_timestamp_in_the_future(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromTimestamp(1_000_000_000_000_000);
    }

    #[Test]
    public function it_fails_to_instantiate_an_out_of_range_timestamp_in_the_past(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromTimestamp(-1_000_000_000_000_000);
    }

    #[Test]
    public function it_fails_to_instantiate_an_invalid_date_format_string(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDateFormat(DateTimeInterface::ATOM, '2012-02-03');
    }

    #[Test]
    public function it_fails_to_instantiate_a_date_with_an_invalid_timezone(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDateString('+ 5 minutes', 'foobar');
    }

    #[Test]
    public function it_fails_to_instantiate_a_date_with_an_invalid_datetime(): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromDateString('foobar');
    }

    #[Test]
    public function it_instantiates_a_decimal(): void
    {
        self::assertSame(42.0, Item::new(42.0)->value());
        self::assertSame(42.0, Item::fromDecimal(42)->value());
        self::assertSame(42.0, Item::fromDecimal(42.0)->value());
    }

    #[Test]
    public function it_instantiates_a_integer(): void
    {
        self::assertSame(42, Item::new(42)->value());
        self::assertSame(42, Item::fromInteger(42.9)->value());
        self::assertSame(42, Item::fromInteger(42.0)->value());
        self::assertSame(42, Item::fromInteger(42)->value());
    }

    #[Test]
    public function it_instantiates_a_boolean(): void
    {
        self::assertTrue(Item::new(true)->value());
        self::assertTrue(Item::true()->value());
        self::assertFalse(Item::new(false)->value());
        self::assertFalse(Item::false()->value());
    }

    #[Test]
    public function it_instantiates_a_binary(): void
    {
        $byteSequence = ByteSequence::fromDecoded('foobar');

        self::assertEquals($byteSequence, Item::new(ByteSequence::fromDecoded('foobar'))->value());
        self::assertEquals($byteSequence, Item::fromDecodedByteSequence('foobar')->value());
        self::assertEquals($byteSequence, Item::fromEncodedByteSequence('Zm9vYmFy')->value());
    }

    #[Test]
    public function it_instantiates_a_string(): void
    {
        self::assertSame('"foobar"', Item::fromString('foobar')->toHttpValue());
    }

    #[Test]
    #[DataProvider('itemTypeProvider')]
    public function it_can_tell_the_item_type(Item $item, Type $expectedType): void
    {
        self::assertTrue($item->type()->equals($expectedType));
    }

    /**
     * @return iterable<string, array{item:Item, expectedType:Type}>
     */
    public static function itemTypeProvider(): iterable
    {
        return [
            'boolean' => [
                'item' => Item::new(false),
                'expectedType' => Type::Boolean,
            ],
            'integer' => [
                'item' => Item::new(42),
                'expectedType' => Type::Integer,
            ],
            'decimal' => [
                'item' => Item::new(42.0),
                'expectedType' => Type::Decimal,
            ],
            'string' => [
                'item' => Item::new('42'),
                'expectedType' => Type::ByteSequence,
            ],
            'token' => [
                'item' => Item::new(Token::fromString('forty-two')),
                'expectedType' => Type::Token,
            ],
            'byte' => [
                'item' => Item::new(ByteSequence::fromDecoded('😊')),
                'expectedType' => Type::ByteSequence,
            ],
            'date-immutable' => [
                'item' => Item::new(new DateTimeImmutable('2020-07-12 13:37:00')),
                'expectedType' => Type::Date,
            ],
            'date-interface' => [
                'item' => Item::new(new DateTime('2020-07-12 13:37:00')),
                'expectedType' => Type::Date,
            ],
        ];
    }

    #[Test]
    public function in_can_be_instantiated_using_bare_items(): void
    {
        $parameters = [
            'string' => '42',
            'integer' => 42,
            'float' => 4.2,
            'boolean' => true,
            'token' => Token::fromString('forty-two'),
            'byte-sequence' => ByteSequence::fromDecoded('a42'),
            'date' => new DateTimeImmutable('2020-12-01 11:43:17'),
        ];

        self::assertEquals(
            Item::fromAssociative('/terms', $parameters),
            Item::fromAssociative('/terms', new ArrayObject($parameters))
        );
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
        self::assertFalse($instance->parameters()->get('b')->value());
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
        $instance1 = Item::new(Token::fromString('babayaga'));
        $instance3 = Item::fromPair([Token::fromString('babayaga'), []]);

        self::assertEquals($instance3, $instance1);
    }

    #[Test]
    public function it_can_create_and_return_an_array_of_pairs(): void
    {
        $instance = Item::fromPair([42, [['foo', 'bar']]]);
        $res = $instance->toPair();

        self::assertSame(42, $res[0]);
        self::assertEquals(Parameters::fromAssociative(['foo' => 'bar']), $res[1]);
        self::assertEquals($instance, Item::fromPair($res));
    }

    #[Test]
    #[DataProvider('invalidPairProvider')]
    /**
     * @param array<int|string|array<int|string>> $pair
     */
    public function it_fails_to_create_an_item_from_an_array_of_pairs(array $pair): void
    {
        $this->expectException(SyntaxError::class);

        Item::fromPair($pair);  // @phpstan-ignore-line
    }

    /**
     * @return iterable<string, array{pair:array<int|string|array<int|string>>}>
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
        $instance1 = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = Item::fromPair([Token::fromString('babayaga'), [['a', true]]]);

        self::assertEquals($instance2, $instance1);
    }

    #[Test]
    public function it_can_create_via_with_value_method_a_new_object(): void
    {
        $instance1 = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = $instance1->withValue(Token::fromString('babayaga'));

        self::assertSame($instance1, $instance2);
        self::assertSame($instance1->parameters(), $instance2->parameters());
    }

    #[Test]
    public function it_can_create_via_with_parameters_method_a_new_object(): void
    {
        $instance1 = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);
        $instance2 = $instance1->withParameters(Parameters::fromAssociative(['a' => true]));
        $instance3 = $instance1->withParameters(Parameters::fromAssociative(['a' => false]));

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1, $instance3);
        self::assertEquals($instance1->value(), $instance3->value());
    }

    #[Test]
    public function it_can_create_via_parameters_access_methods_a_new_object(): void
    {
        $instance1 = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);
        $instance7 = $instance1->addParameter('a', true);
        $instance2 = $instance1->appendParameter('a', true);
        $instance3 = $instance1->prependParameter('a', false);
        $instance4 = $instance1->withoutParameterByKeys('b');
        $instance5 = $instance1->withoutParameterByKeys('a');
        $instance6 = $instance1->withoutAnyParameter();

        self::assertSame($instance1, $instance2);
        self::assertSame($instance1, $instance7);
        self::assertNotSame($instance1, $instance3);
        self::assertEquals($instance1->value(), $instance3->value());
        self::assertSame($instance1, $instance4);
        self::assertTrue($instance1->parameter('a'));
        self::assertSame(['a', true], $instance1->parameterByIndex(0));
        self::assertNull($instance5->parameter('a'));
        self::assertTrue($instance5->parameters()->hasNoMembers());
        self::assertTrue($instance6->parameters()->hasNoMembers());
        self::assertNull($instance1->parameter('non-existing-key'));
        self::assertSame([], $instance1->parameterByIndex(42));
    }

    #[Test]
    public function it_can_create_a_new_instance_using_parameters_position_modifying_methods(): void
    {
        $instance1 = Item::new(Token::fromString('babayaga'));
        $instance2 = $instance1
            ->pushParameters(['a', true], ['v', ByteSequence::fromDecoded('I will be removed')], ['c', 'true'])
            ->unshiftParameters(['b', Item::false()])
            ->replaceParameter(1, ['a', 'false'])
            ->withoutParameterByIndices(-2)
            ->insertParameters(1, ['d', Token::fromString('*/*')]);

        self::assertTrue($instance1->parameters()->hasNoMembers());
        self::assertTrue($instance2->parameters()->hasMembers());
        self::assertCount(4, $instance2->parameters());
        self::assertEquals(['d', Token::fromString('*/*')], $instance2->parameterByIndex(1));
        self::assertSame(['b', false], $instance2->parameterByIndex(0));
        self::assertSame(['c', 'true'], $instance2->parameterByIndex(-1));
        self::assertSame(';b=?0;d=*/*;a="false";c="true"', $instance2->parameters()->toHttpValue());
    }
}
