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
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OuterList::from(...$arrayParams);

        self::assertSame($stringItem, $instance->get(0));
        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->hasNoMembers());
        self::assertEquals($arrayParams, [...$instance]);
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OuterList::from(...$arrayParams);

        self::assertCount(2, $instance);
        self::assertSame($booleanItem, $instance->get(1));
        self::assertTrue($instance->has(0, 1));

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

    #[Test]
    public function it_can_unshift_insert_and_replace(): void
    {
        $instance = OuterList::from()
            ->unshift(Item::from('42'))
            ->push(Item::from(42))
            ->insert(1, Item::from(42.0))
            ->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertTrue($instance->hasMembers());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $instance->toHttpValue());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', (string) $instance);
    }

    #[Test]
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        OuterList::from()->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    #[Test]
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        OuterList::from()->insert(3, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = OuterList::from();

        self::assertFalse($instance->has(3));

        $this->expectException(InvalidOffset::class);

        $instance->get(3);
    }

    #[Test]
    public function it_can_generate_the_same_value(): void
    {
        $res = OuterList::fromHttpValue('token, "string", ?1; parameter, (42 42.0)');

        $list = OuterList::from(...[
            Token::fromString('token'),
            'string',
            Item::from(true, ['parameter' => true]),
            InnerList::from(42, 42.0),
        ]);

        self::assertSame($res->toHttpValue(), $list->toHttpValue());
    }

    #[Test]
    public function it_fails_to_insert_unknown_index_via_the_array_access_interface(): void
    {
        $this->expectException(StructuredFieldError::class);

        OuterList::from()->insert(0, Item::from(42.0));
    }

    #[Test]
    public function it_returns_the_same_object_if_nothing_is_changed(): void
    {
        $container = OuterList::from(42, 'forty-two');

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

        OuterList::from()->get('zero');
    }

    #[Test]
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $innerList = InnerList::from('test');
        $input = ['foobar', 0, false, $token, $innerList];
        $structuredField = OuterList::from(...$input);

        self::assertInstanceOf(Item::class, $structuredField->get(2));
        self::assertFalse($structuredField->get(2)->value());

        self::assertInstanceOf(InnerList::class, $structuredField->get(-1));
        self::assertFalse($structuredField->has('foobar'));

        self::assertEquals(Item::from('barbaz'), $structuredField->push('barbaz')->get(-1));
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $structuredField = OuterList::from('foobar', 'foobar', 'zero', 0);

        self::assertInstanceOf(Item::class, $structuredField->get(0));
        self::assertInstanceOf(Item::class, $structuredField[0]);

        self::assertFalse(isset($structuredField[42]));
    }

    #[Test]
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(OuterList::from('foobar', 'foobar', 'zero', 0)[0]);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        OuterList::from('foobar', 'foobar', 'zero', 0)[0] = Item::from(false);
    }


    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = OuterList::from();

        self::assertSame([], $instance->keys());

        $newInstance = $instance
            ->push(Item::from(false), Item::from(true));

        self::assertSame([0, 1], $newInstance->keys());

        $container = OuterList::from()
            ->unshift('42')
            ->push(42)
            ->insert(1, 42.0)
            ->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertSame([0, 1, 2], $container->keys());
    }
}
