<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\OrderedList
 */
final class OrderedListTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/list.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/listlist.json',
    ];

    /** @test */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OrderedList::fromList($arrayParams);

        self::assertSame($stringItem, $instance->get(0));
        self::assertTrue($instance->hasMembers());
        self::assertEquals($arrayParams, iterator_to_array($instance));
    }

    /** @test */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OrderedList::fromList($arrayParams);

        self::assertCount(2, $instance);
        self::assertSame($booleanItem, $instance->get(1));

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->has(1));

        $instance->push(Item::from('BarBaz'));
        $member = $instance->get(1);

        self::assertCount(2, $instance);
        self::assertInstanceOf(Item::class, $member);
        self::assertIsString($member->value());
        self::assertStringContainsString('BarBaz', $member->value());

        $instance->remove(0, 1);
        self::assertCount(0, $instance);
        self::assertFalse($instance->hasMembers());
    }

    /** @test */
    public function it_can_unshift_insert_and_replace(): void
    {
        $instance = OrderedList::fromList();
        $instance->unshift(Item::from('42'));
        $instance->push(Item::from(42));
        $instance->insert(1, Item::from(42.0));
        $instance->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertTrue($instance->hasMembers());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $instance->toHttpValue());

        $instance->clear();
        self::assertFalse($instance->hasMembers());
    }

    /** @test */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = OrderedList::fromList();
        $container->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /** @test */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = OrderedList::fromList();
        $container->insert(3, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /** @test */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = OrderedList::fromList();
        self::assertFalse($instance->has(3));

        $instance->get(3);
    }

    /** @test */
    public function test_it_can_generate_the_same_value(): void
    {
        $res = OrderedList::fromHttpValue('token, "string", ?1; parameter, (42 42.0)');

        $list = OrderedList::fromList([
            Token::fromString('token'),
            'string',
            Item::from(true, ['parameter' => true]),
            InnerList::fromList([42, 42.0]),
        ]);

        self::assertSame($res->toHttpValue(), $list->toHttpValue());
    }

    /** @test */
    public function it_implements_the_array_access_interface(): void
    {
        $sequence = OrderedList::fromList();
        $sequence[] = InnerList::from(42, 69); // @phpstan-ignore-line

        self::assertTrue(isset($sequence[0]));
        self::assertInstanceOf(InnerList::class, $sequence[0]);
        self::assertEquals(42, $sequence[0]->get(0)->value());

        $sequence[0] = false;

        self::assertEquals(Item::from(false), $sequence[0]);
        unset($sequence[0]);

        self::assertCount(0, $sequence);
        self::assertFalse(isset($sequence[0]));
    }

    /** @test */
    public function it_fails_to_insert_unknown_index_via_the_array_access_interface(): void
    {
        $this->expectException(StructuredFieldError::class);

        $sequence = OrderedList::fromList();
        $sequence[0] = Item::from(42.0);
    }

    /** @test */
    public function testArrayAccessThrowsInvalidIndex2(): void
    {
        $sequence = OrderedList::from();
        unset($sequence[0]);

        self::assertCount(0, $sequence);
    }

    /** @test */
    public function it_fails_http_conversion_with_invalid_parameters(): void
    {
        $this->expectException(StructuredFieldError::class);

        $structuredField = OrderedList::fromList();
        $structuredField[] = 42;
        $item = $structuredField[0];
        $item->parameters->append('forty-two', '42');
        $wrongUpdatedItem = $item->parameters->get('forty-two');
        $wrongUpdatedItem->parameters->append('invalid-value', 'not-valid');
        self::assertCount(1, $wrongUpdatedItem->parameters);

        $structuredField->toHttpValue();
    }

    /** @test */
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(InvalidOffset::class);

        $structuredField = OrderedList::from();
        $structuredField->get('zero');
    }

    /** @test */
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $innerList = InnerList::from('test');
        $input = ['foobar', 0, false, $token, $innerList];
        $structuredField = OrderedList::fromList($input);

        self::assertSame(['foobar', 0, false, 'token', [0 => 'test']], $structuredField->values());
        self::assertFalse($structuredField->value(2));
        self::assertNull($structuredField->value(42));
        self::assertNull($structuredField->value('2'));
        self::assertSame([0 => 'test'], $structuredField->value(-1));
    }

    /** @test */
    public function it_will_strip_invalid_state_object_via_values_methods(): void
    {
        $bar = Item::from(Token::fromString('bar'));
        $bar->parameters->set('baz', 42);
        $structuredField = OrderedList::from(false, $bar);
        $structuredField[1]->parameters['baz']->parameters->set('error', 'error');

        self::assertNull($structuredField->value(1));
        self::assertEquals([false], $structuredField->values());
    }
}
