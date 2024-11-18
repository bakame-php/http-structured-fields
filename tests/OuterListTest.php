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

        self::assertSame($stringItem, $instance->getByIndex(0));
        self::assertTrue($instance->isNotEmpty());
        self::assertFalse($instance->isEmpty());
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
        self::assertSame($booleanItem, $instance->getByIndex(1));
        self::assertTrue($instance->hasIndices(0, 1));

        $deletedInstance = $instance->removeByIndices(1);

        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->hasIndices(1));

        $newInstance = $deletedInstance->push(Item::fromString('BarBaz'));
        $member = $newInstance->getByIndex(1);

        self::assertCount(2, $newInstance);
        self::assertInstanceOf(Item::class, $member);
        self::assertIsString($member->value());
        self::assertStringContainsString('BarBaz', $member->value());

        $altInstance = $newInstance->removeByIndices(0, 1);

        self::assertCount(0, $altInstance);
        self::assertTrue($altInstance->isEmpty());
        self::assertFalse($altInstance->isNotEmpty());
    }

    #[Test]
    public function it_can_unshift_insert_and_replace(): void
    {
        $anonymous = new class () implements StructuredFieldProvider {
            public function toStructuredField(): Item
            {
                return Item::fromInteger(42);
            }
        };

        $instance = OuterList::new()
            ->unshift(Item::fromString('42'))
            ->push($anonymous)
            ->insert(1, Item::fromDecimal(42.0))
            ->replace(0, Item::new(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertTrue($instance->isNotEmpty());
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
    public function it_can_return_the_same_object_if_no_replace_is_needed(): void
    {
        $item = Item::new(ByteSequence::fromDecoded('Hello World'));
        $field = OuterList::new($item);

        self::assertSame($field, $field->replace(0, ByteSequence::fromDecoded('Hello World')));
    }

    #[Test]
    public function it_fails_to_insert_something_other_than_a_inner_list_or_an_item(): void
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

        self::assertFalse($instance->hasIndices(3));

        $this->expectException(InvalidOffset::class);

        $instance->getByIndex(3);
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
            ->removeByIndices(42, 46);

        self::assertSame($container, $sameContainer);
    }

    #[Test]
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $innerList = InnerList::new('test');
        $input = ['foobar', 0, false, $token, $innerList];
        $structuredField = OuterList::new(...$input);

        self::assertInstanceOf(Item::class, $structuredField->getByIndex(2));
        self::assertFalse($structuredField->getByIndex(2)->value());

        self::assertInstanceOf(InnerList::class, $structuredField->getByIndex(-1));
        self::assertEquals(Item::fromString('barbaz'), $structuredField->push('barbaz')->getByIndex(-1));
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $structuredField = OuterList::new('foobar', 'foobar', 'zero', 0);

        self::assertInstanceOf(Item::class, $structuredField->getByIndex(0));
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

        self::assertSame([], $instance->indices());

        $newInstance = $instance
            ->push(Item::false(), Item::true());

        self::assertSame([0, 1], $newInstance->indices());

        $container = OuterList::new()
            ->unshift('42')
            ->push(42)
            ->insert(1, 42.0)
            ->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertSame([0, 1, 2], $container->indices());
    }
}
