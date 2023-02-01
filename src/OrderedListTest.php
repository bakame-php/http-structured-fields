<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\OrderedList
 */
final class OrderedListTest extends StructuredFieldTest
{
    /** @var array<string> */
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
        self::assertFalse($instance->hasNoMembers());
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

        $deletedInstance = $instance->remove(1);

        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->has(1));

        $newInstance = $deletedInstance->push(Item::from('BarBaz'));
        $member = $newInstance->get(1);

        self::assertCount(2, $newInstance);
        self::assertInstanceOf(Item::class, $member);
        self::assertIsString($member->value());
        self::assertStringContainsString('BarBaz', $member->value());

        $altInstance = $newInstance->remove(0, 1);

        self::assertCount(0, $altInstance);
        self::assertTrue($altInstance->hasNoMembers());
        self::assertFalse($altInstance->hasMembers());
    }

    /** @test */
    public function it_can_unshift_insert_and_replace(): void
    {
        $instance = OrderedList::from()
            ->unshift(Item::from('42'))
            ->push(Item::from(42))
            ->insert(1, Item::from(42.0))
            ->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertTrue($instance->hasMembers());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $instance->toHttpValue());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', (string) $instance);
    }

    /** @test */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        OrderedList::from()->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /** @test */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        OrderedList::from()->insert(3, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /** @test */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = OrderedList::from();

        self::assertFalse($instance->has(3));

        $this->expectException(InvalidOffset::class);

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
    public function it_fails_to_insert_unknown_index_via_the_array_access_interface(): void
    {
        $this->expectException(StructuredFieldError::class);

        OrderedList::from()->insert(0, Item::from(42.0));
    }

    /** @test */
    public function testArrayAccessThrowsInvalidIndex2(): void
    {
        self::assertCount(0, OrderedList::from()->remove(0));
    }

    /** @test */
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(InvalidOffset::class);

        OrderedList::from()->get('zero');
    }

    /** @test */
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $innerList = InnerList::from('test');
        $input = ['foobar', 0, false, $token, $innerList];
        $structuredField = OrderedList::fromList($input);

        self::assertInstanceOf(Item::class, $structuredField->get(2));
        self::assertFalse($structuredField->get(2)->value());

        self::assertInstanceOf(InnerList::class, $structuredField->get(-1));
        self::assertFalse($structuredField->has('foobar'));

        self::assertEquals(Item::from('barbaz'), $structuredField->push('barbaz')->get(-1));
    }

    /** @test */
    public function it_implements_the_array_access_interface(): void
    {
        $structuredField = OrderedList::from('foobar', 'foobar', 'zero', 0);

        self::assertInstanceOf(Item::class, $structuredField->get(0));
        self::assertInstanceOf(Item::class, $structuredField[0]);

        self::assertFalse(isset($structuredField[42]));
    }

    /** @test */
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(OrderedList::from('foobar', 'foobar', 'zero', 0)[0]);
    }

    /** @test */
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        OrderedList::from('foobar', 'foobar', 'zero', 0)[0] = Item::from(false);
    }
}
