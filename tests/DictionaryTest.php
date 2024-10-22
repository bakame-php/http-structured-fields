<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;
use PHPUnit\Framework\Attributes\Test;

final class DictionaryTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'dictionary.json',
    ];

    #[Test]
    public function it_can_be_instantiated_with_an_collection_of_item_or_inner_list(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Dictionary::fromAssociative($arrayParams);


        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string', 'string'));
        self::assertFalse($instance->has('string', 'no-present'));
        self::assertEquals([['string', $stringItem], ['boolean', $booleanItem]], [...$instance->toPairs()]);
        self::assertEquals($arrayParams, [...$instance]);
    }

    #[Test]
    public function it_can_be_instantiated_with_key_value_pairs(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = [['string', $stringItem], ['boolean', $booleanItem]];
        $instance = Dictionary::fromPairs($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));
        self::assertEquals($arrayParams, [...$instance->toPairs()]);
        self::assertEquals(['string' => $stringItem, 'boolean' => $booleanItem], [...$instance]);
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Dictionary::fromAssociative($arrayParams);

        self::assertCount(2, $instance);
        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->hasNoMembers());

        $deletedInstance = $instance->remove('boolean');

        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->has('boolean'));
        self::assertFalse($deletedInstance->hasPair(1));

        $appendInstance = $deletedInstance->append('foobar', Item::new('BarBaz'));
        self::assertTrue($appendInstance->hasPair(1));

        self::assertSame($appendInstance, $appendInstance->append('foobar', Item::new('BarBaz')));

        /** @var array{0:string, 1:Item} $foundItem */
        $foundItem = $appendInstance->pair(1);

        self::assertIsString($foundItem[1]->value());
        self::assertStringContainsString('BarBaz', $foundItem[1]->value());

        $deleteAgain = $appendInstance->remove('foobar', 'string');

        self::assertCount(0, $deleteAgain);
        self::assertFalse($deleteAgain->hasMembers());
    }

    #[Test]
    public function it_returns_the_same_object_if_no_member_is_removed(): void
    {
        $instance = Dictionary::new();

        self::assertSame($instance, $instance->remove('foo', 'bar', 'baz'));
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $instance = Dictionary::new();

        self::assertFalse($instance->has('foobar'));

        $this->expectException(InvalidOffset::class);

        $instance->get('foobar');
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = Dictionary::new();

        self::assertFalse($instance->hasPair(1, 2, 3));
        self::assertFalse($instance->hasPair());

        $this->expectException(InvalidOffset::class);

        $instance->pair(3);
    }

    #[Test]
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        Dictionary::fromAssociative(['bébé' => Item::false()]);
    }

    #[Test]
    public function it_fails_to_insert_something_other_than_a_inner_list_or_an_item(): void
    {
        $this->expectException(InvalidArgument::class);

        Dictionary::new()->add('foo', Parameters::fromAssociative(['foo' => 'bar']));
    }

    #[Test]
    public function it_can_prepend_a_new_member(): void
    {
        $instance = Dictionary::new()
            ->append('a', Item::false())
            ->prepend('b', Item::true());

        self::assertSame('b, a=?0', (string) $instance);
    }

    #[Test]
    public function it_can_prepend_a_new_member_without_changing(): void
    {
        $instance = Dictionary::new()->append('b', Item::true());
        $instance2 = $instance->prepend('b', Item::true());

        self::assertSame($instance2, $instance);
    }

    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = Dictionary::new();

        self::assertSame([], $instance->keys());

        $newInstance = $instance
            ->append('a', Item::false())
            ->prepend('b', Item::true());

        self::assertSame(['b', 'a'], $newInstance->keys());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances_using_associative(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::false()]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::true()]);
        $instance3 = Dictionary::fromAssociative(['a' => Item::new(42)]);

        $instance4 = $instance1->mergeAssociative($instance2, $instance3);

        self::assertCount(2, $instance4);
        self::assertEquals(Item::new(42), $instance4->get('a'));
        self::assertEquals(Item::true(), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::false()]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::true()]);
        $instance3 = Dictionary::fromAssociative(['a' => Item::new(42)]);

        $instance4 = $instance3->mergeAssociative($instance2, $instance1);

        self::assertCount(2, $instance4);
        self::assertEquals(Item::false(), $instance4->get('a'));
        self::assertEquals(Item::true(), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        self::assertCount(1, Dictionary::fromAssociative(['a' => Item::false()])->mergeAssociative());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances_using_pairs(): void
    {
        $instance1 = Dictionary::fromPairs([['a', Item::false()]]);
        $instance2 = Dictionary::fromPairs([['b', Item::true()]]);
        $instance3 = Dictionary::fromPairs([['a', Item::new(42)]]);

        $instance4 = $instance3->mergePairs($instance2, $instance1);

        self::assertCount(2, $instance4);

        self::assertEquals(Item::false(), $instance4->get('a'));
        self::assertEquals(Item::true(), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_without_pairs_and_not_throw(): void
    {
        $instance = Dictionary::fromAssociative(['a' => Item::false()]);

        self::assertCount(1, $instance->mergePairs());
    }

    #[Test]
    public function it_can_merge_dictionary_instances_via_pairs_or_associative(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::false()]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::true()]);

        $instance3 = clone $instance1;
        $instance4 = clone $instance2;

        self::assertEquals($instance3->mergeAssociative($instance4), $instance1->mergePairs($instance2));
    }

    #[Test]
    public function it_can_handle_string_with_comma(): void
    {
        $expected = 'a=foobar;test="bar, baz", b=toto';
        $instance = Dictionary::fromHttpValue($expected);

        self::assertSame($expected, $instance->toHttpValue());
        self::assertCount(2, $instance);
    }

    #[Test]
    public function it_can_delete_a_member_via_remove_method(): void
    {
        $newInstance = Dictionary::new()->add('foo', 'bar');

        self::assertTrue($newInstance->hasMembers());
        self::assertCount(1, $newInstance);

        $newInstance2 = $newInstance->add('foo', 'bar');
        self::assertCount(1, $newInstance2);
        self::assertSame($newInstance, $newInstance2);

        self::assertFalse($newInstance->remove('foo')->hasMembers());
        self::assertSame($newInstance, $newInstance->remove('baz', 'bar', 'yolo-not-there', 325));
        self::assertSame($newInstance, $newInstance->removeByKeys('baz', 'bar', 'yolo-not-there'));
        self::assertSame($newInstance, $newInstance->removeByIndices(325));

        $instanceWithoutMember = Dictionary::new();
        self::assertSame($instanceWithoutMember->remove(), $instanceWithoutMember);
    }

    #[Test]
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(StructuredFieldError::class);

        Dictionary::new()->get(0);
    }

    #[Test]
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');

        $structuredField = Dictionary::fromPairs([
            ['foobar', 'foobar'],
            ['zero', 0],
            ['false', false],
            ['token', $token],
        ]);

        self::assertInstanceOf(Item::class, $structuredField->get('false'));
        self::assertFalse($structuredField->get('false')->value());
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $token = Token::fromString('token');

        $structuredField = Dictionary::fromPairs([
            ['foobar', 'foobar'],
            ['zero', 0],
            ['false', false],
            ['token', $token],
        ]);

        self::assertInstanceOf(Item::class, $structuredField->get('false'));
        self::assertInstanceOf(Item::class, $structuredField['false']);

        self::assertFalse($structuredField->get('false')->value());
        self::assertFalse($structuredField['false']->value());
        self::assertFalse(isset($structuredField['toto']));
    }

    #[Test]
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(Dictionary::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar']);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        Dictionary::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar'] = Item::false();
    }

    #[Test]
    public function it_can_returns_the_container_member_keys_with_pairs(): void
    {
        $instance = Dictionary::new();

        self::assertSame([], $instance->keys());
        self::assertSame(['a', 'b'], $instance->push(['a', false], ['b', true])->keys());

        $container = Dictionary::new()
            ->unshift(['a', '42'])
            ->push(['b', 42])
            ->insert(1, ['c', 42.0])
            ->replace(0, ['d', 'forty-two']);

        self::assertSame(['d', 'c', 'b'], $container->keys());
        self::assertSame('d="forty-two", c=42.0, b=42', $container->toHttpValue());
    }

    #[Test]
    public function it_can_push_and_unshift_new_pair(): void
    {
        $instance = Dictionary::new()
            ->push(['a', false])
            ->unshift(['b', true]);

        self::assertSame('b, a=?0', $instance->toHttpValue());
        self::assertSame('b, a=?0', (string) $instance);
    }

    #[Test]
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        Dictionary::new()->insert(3, ['a', 1]);
    }

    #[Test]
    public function it_can_push_nothing(): void
    {
        self::assertEquals(Dictionary::new()->push()->unshift(), Dictionary::new());
    }

    #[Test]
    public function it_fails_to_replace_unknown_index(): void
    {
        $this->expectException(InvalidOffset::class);

        Dictionary::new()->replace(0, ['a', true]);
    }

    #[Test]
    public function it_returns_the_same_instance_if_nothing_is_replaced(): void
    {
        $field = Dictionary::new()->push(['a', true]);

        self::assertSame($field->replace(0, ['a', true]), $field);
    }

    #[Test]
    public function it_can_create_a_new_instance_using_parameters_position_modifying_methods(): void
    {
        $instance = new class () implements StructuredFieldProvider {
            public function toStructuredField(): StructuredField
            {
                return Item::false();
            }
        };

        $instance1 = Dictionary::new();
        $instance2 = $instance1
            ->push(['a', true], ['v', ByteSequence::fromDecoded('I will be removed')], ['c', 'true'])
            ->unshift(['b', $instance])
            ->replace(1, ['a', 'false'])
            ->remove(-2, 'toto')
            ->insert(1, ['d', Token::fromString('*/*')]);

        self::assertTrue($instance1->hasNoMembers());
        self::assertTrue($instance2->hasMembers());
        self::assertCount(4, $instance2);
        self::assertEquals(['d', Item::fromToken('*/*')], $instance2->pair(1));
        self::assertEquals(['b', Item::false()], $instance2->pair(0));
        self::assertEquals(['c', Item::fromString('true')], $instance2->pair(-1));
        self::assertSame('b=?0, d=*/*, a="false", c="true"', $instance2->toHttpValue());
    }
}
