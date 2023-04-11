<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;
use PHPUnit\Framework\Attributes\Test;

final class OuterListTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'list.json',
        'listlist.json',
    ];

    #[Test]
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::new('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OuterList::new(...$arrayParams);

        self::assertSame($stringItem, $instance->get(0));
        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->hasNoMembers());
        self::assertEquals($arrayParams, [...$instance]);
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::new('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OuterList::new(...$arrayParams);

        self::assertCount(2, $instance);
        self::assertSame($booleanItem, $instance->get(1));
        self::assertTrue($instance->has(0, 1));

        $deletedInstance = $instance->remove(1);

        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->has(1));

        $newInstance = $deletedInstance->push(Item::fromString('BarBaz'));
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

    #[Test]
    public function it_can_unshift_insert_and_replace(): void
    {
        $instance = OuterList::new()
            ->unshift(Item::fromString('42'))
            ->push(Item::fromInteger(42))
            ->insert(1, Item::fromDecimal(42.0))
            ->replace(0, Item::new(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertTrue($instance->hasMembers());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $instance->toHttpValue());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', (string) $instance);
    }

    #[Test]
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        OuterList::new()->replace(0, Item::new(ByteSequence::fromDecoded('Hello World')));
    }

    #[Test]
    public function it_fails_to_insert_somethine_other_than_a_inner_list_or_an_item(): void
    {
        $this->expectException(InvalidArgument::class);

        OuterList::new()->push(Dictionary::fromAssociative(['foo' => 'bar']));
    }

    #[Test]
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        OuterList::new()->insert(3, Item::new(ByteSequence::fromDecoded('Hello World')));
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = OuterList::new();

        self::assertFalse($instance->has(3));

        $this->expectException(InvalidOffset::class);

        $instance->get(3);
    }

    #[Test]
    public function it_can_generate_the_same_value(): void
    {
        $res = OuterList::fromHttpValue('token, "string", ?1; parameter, (42 42.0)');

        $list = OuterList::new(...[
            Token::fromString('token'),
            'string',
            Item::fromAssociative(true, ['parameter' => true]),
            InnerList::new(42, 42.0),
        ]);

        self::assertSame($res->toHttpValue(), $list->toHttpValue());
    }

    #[Test]
    public function it_fails_to_insert_unknown_index_via_the_array_access_interface(): void
    {
        $this->expectException(StructuredFieldError::class);

        OuterList::new()->insert(0, Item::fromDecimal(42.0));
    }

    #[Test]
    public function it_returns_the_same_object_if_nothing_is_changed(): void
    {
        $container = OuterList::new(42, 'forty-two');

        $sameContainer = $container
            ->unshift()
            ->push()
            ->insert(1)
            ->remove(42, 46);

        self::assertSame($container, $sameContainer);
    }

    #[Test]
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(InvalidOffset::class);

        OuterList::new()->get('zero');
    }

    #[Test]
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $innerList = InnerList::new('test');
        $input = ['foobar', 0, false, $token, $innerList];
        $structuredField = OuterList::new(...$input);

        self::assertInstanceOf(Item::class, $structuredField->get(2));
        self::assertFalse($structuredField->get(2)->value());

        self::assertInstanceOf(InnerList::class, $structuredField->get(-1));
        self::assertFalse($structuredField->has('foobar'));

        self::assertEquals(Item::fromString('barbaz'), $structuredField->push('barbaz')->get(-1));
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $structuredField = OuterList::new('foobar', 'foobar', 'zero', 0);

        self::assertInstanceOf(Item::class, $structuredField->get(0));
        self::assertInstanceOf(Item::class, $structuredField[0]);

        self::assertFalse(isset($structuredField[42]));
    }

    #[Test]
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(OuterList::new('foobar', 'foobar', 'zero', 0)[0]);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        OuterList::new('foobar', 'foobar', 'zero', 0)[0] = Item::false();
    }


    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = OuterList::new();

        self::assertSame([], $instance->keys());

        $newInstance = $instance
            ->push(Item::false(), Item::true());

        self::assertSame([0, 1], $newInstance->keys());

        $container = OuterList::new()
            ->unshift('42')
            ->push(42)
            ->insert(1, 42.0)
            ->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertSame([0, 1, 2], $container->keys());
    }
}
