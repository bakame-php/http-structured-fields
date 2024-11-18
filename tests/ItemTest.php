<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayObject;
use Bakame\Http\StructuredFields\Validation\Violation;
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
    public function it_instantiate_many_types(ByteSequence|Token|DisplayString|DateTimeInterface|string|int|float|bool $value, string $expected): void
    {
        self::assertSame($expected, Item::new($value)->toHttpValue());
    }

    #[Test]
    #[DataProvider('provideFrom1stArgument')]
    public function it_updates_item(ByteSequence|Token|DisplayString|DateTimeInterface|string|int|float|bool $value, string $expected): void
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
            'detail string' => ['value' => DisplayString::fromDecoded('😊'), 'expected' => '%"%f0%9f%98%8a"'],
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
    public function it_instantiates_a_display_string(): void
    {
        $displayString = DisplayString::fromDecoded('😊');

        self::assertEquals($displayString, Item::new(DisplayString::fromDecoded('😊'))->value());
        self::assertEquals($displayString, Item::fromDecodedDisplayString('😊')->value());
        self::assertEquals($displayString, Item::fromEncodedDisplayString('%f0%9f%98%8a')->value());
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
                'expectedType' => Type::String,
            ],
            'display string' => [
                'item' => Item::new(DisplayString::fromDecoded('😊')),
                'expectedType' => Type::DisplayString,
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
            'displaystring' => DisplayString::fromDecoded('😊'),
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

        self::assertTrue($instance->parameters()->getByName('a')->value());
        self::assertFalse($instance->parameters()->getByName('b')->value());
    }

    #[Test]
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $this->expectException(StructuredFieldError::class);

        Item::fromHttpValue('1; a; b=?0')->parameters()->getByName('bar')->value();
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

        Item::fromPair($pair);
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
        $instance4 = $instance1->withoutParameterByNames('b');
        $instance5 = $instance1->withoutParameterByNames('a');
        $instance6 = $instance1->withoutAnyParameter();

        self::assertSame($instance1, $instance2);
        self::assertSame($instance1, $instance7);
        self::assertNotSame($instance1, $instance3);
        self::assertEquals($instance1->value(), $instance3->value());
        self::assertSame($instance1, $instance4);
        self::assertTrue($instance1->parameterByName('a'));
        self::assertSame(['a', true], $instance1->parameterByIndex(0));
        self::assertNull($instance5->parameterByName('a'));
        self::assertTrue($instance5->parameters()->isEmpty());
        self::assertTrue($instance6->parameters()->isEmpty());
        self::assertNull($instance1->parameterByName('non-existing-key'));
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

        self::assertTrue($instance1->parameters()->isEmpty());
        self::assertTrue($instance2->parameters()->isNotEmpty());
        self::assertCount(4, $instance2->parameters());
        self::assertEquals(['d', Token::fromString('*/*')], $instance2->parameterByIndex(1));
        self::assertSame(['b', false], $instance2->parameterByIndex(0));
        self::assertSame(['c', 'true'], $instance2->parameterByIndex(-1));
        self::assertSame(';b=?0;d=*/*;a="false";c="true"', $instance2->parameters()->toHttpValue());
    }

    #[Test]
    public function it_can_validate_the_item_value(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);
        self::assertInstanceOf(Token::class, $item->value());
        self::assertTrue($item->value()->equals(Token::fromString('babayaga')));
    }

    #[Test]
    public function it_can_validate_and_trigger_a_custom_message_on_error(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation('The exception has been triggered'));

        $item->value(fn (mixed $value): string => 'The exception has been triggered');
    }

    #[Test]
    public function it_can_validate_and_trigger_a_default_message_on_error(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation("The item value 'babayaga' failed validation."));

        $item->value(fn (mixed $value): bool => false);
    }

    #[Test]
    public function it_can_validate_the_item_parameter_value(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);
        self::assertTrue($item->parameterByName('a'));
        self::assertTrue($item->parameterByName('a', fn (mixed $value) => true));
        self::assertFalse($item->parameterByName(key: 'b', default: false));
    }

    #[Test]
    public function it_can_validate_and_trigger_a_custom_error_message(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation('The exception has been triggered'));

        $item->parameterByName(key: 'a', validate:fn (mixed $value): string => 'The exception has been triggered');
    }

    #[Test]
    public function it_can_validate_and_trigger_an_error_message_for_missing_parameter_name(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation("The required parameter 'b' is missing."));

        $item->parameterByName(key: 'b', required: true);
    }

    #[Test]
    public function it_can_validate_and_trigger_a_default_error_message(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation("The parameter 'a' whose value is '?1' failed validation."));

        $item->parameterByName(key: 'a', validate:fn (mixed $value): bool => false);
    }

    #[Test]
    public function it_can_validate_and_trigger_a_default_error_message_for_missing_parameters_by_indices(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation("The required parameter at position '12' is missing."));

        $item->parameterByIndex(index: 12, validate:fn (mixed $value): bool => false, required: true);
    }

    #[Test]
    public function it_can_validate_and_trigger_a_default_error_message_for_parameters_by_indices(): void
    {
        $item = Item::fromAssociative(Token::fromString('babayaga'), ['a' => true]);

        $this->expectExceptionObject(new Violation("The parameter at position '0' whose name is 'a' with the value '?1' failed validation."));

        $item->parameterByIndex(index: 0, validate:fn (mixed $value): bool => false, required: true);
    }
}
