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

    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OrderedList::fromMembers($arrayParams);

        self::assertSame($stringItem, $instance->get(0));
        self::assertFalse($instance->isEmpty());

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OrderedList::fromMembers($arrayParams);

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
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_can_unshift_insert_and_replace(): void
    {
        $instance = OrderedList::fromMembers();
        $instance->unshift(Item::from('42'));
        $instance->push(Item::from(42));
        $instance->insert(1, Item::from(42.0));
        $instance->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertFalse($instance->isEmpty());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $instance->toHttpValue());
        $instance->clear();
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = OrderedList::fromMembers();
        $container->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = OrderedList::fromMembers();
        $container->insert(3, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = OrderedList::fromMembers();
        self::assertFalse($instance->has(3));

        $instance->get(3);
    }

    /**
     * @test
     */
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = OrderedList::fromMembers([Item::from(false)]);
        $instance2 = OrderedList::fromMembers([Item::from(true)]);
        $instance3 = OrderedList::fromMembers([Item::from(42)]);
        $expected = OrderedList::fromMembers([Item::from(false), Item::from(true), Item::from(42)]);

        $instance1->merge($instance2, $instance3);

        self::assertCount(3, $instance1);
        self::assertSame($expected->toHttpValue(), $instance1->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = OrderedList::fromMembers([Item::from(false)]);
        $instance2 = OrderedList::fromMembers([Item::from(true)]);
        $instance3 = OrderedList::fromMembers([Item::from(42)]);
        $expected = OrderedList::fromMembers([Item::from(42), Item::from(true), Item::from(false)]);

        $instance3->merge($instance2, $instance1);

        self::assertCount(3, $instance3);
        self::assertSame($expected->toHttpValue(), $instance3->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = OrderedList::fromMembers([Item::from(false)]);
        $instance->merge();
        self::assertCount(1, $instance);
    }

    /**
     * @test
     */
    public function it_can_be_regenerated_with_eval(): void
    {
        $instance = OrderedList::fromMembers([Item::from(false)]);

        /** @var OrderedList $generatedInstance */
        $generatedInstance = eval('return '.var_export($instance, true).';');

        self::assertEquals($instance, $generatedInstance);
    }

    /**
     * @test
     */
    public function test_it_can_generate_the_same_value(): void
    {
        $res = OrderedList::fromHttpValue('token, "string", ?1; parameter, (42 42.0)');

        $list = OrderedList::fromMembers([
            new Token('token'),
            'string',
            Item::from(true, ['parameter' => true]),
            InnerList::fromMembers([42, 42.0]),
        ]);

        self::assertSame($res->toHttpValue(), $list->toHttpValue());
    }
}
