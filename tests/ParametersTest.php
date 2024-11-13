<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ParametersTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'param-dict.json',
        'param-list.json',
        'param-listlist.json',
    ];

    #[Test]
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Parameters::fromAssociative($arrayParams);

        self::assertSame(['string', $stringItem], $instance->getByIndex(0));
        self::assertTrue($instance->hasKeys('string', 'string'));
        self::assertSame($stringItem, $instance->getByKey('string'));
        self::assertTrue($instance->hasKeys('string'));

        self::assertEquals($arrayParams, [...$instance->toAssociative()]);
    }

    #[Test]
    public function test_it_can_be_instantiated_with_key_value_pairs(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = [['string', $stringItem], ['boolean', $booleanItem]];
        $instance = Parameters::fromPairs($arrayParams);

        self::assertSame(['string', $stringItem], $instance->getByIndex(0));
        self::assertSame($stringItem, $instance->getByKey('string'));
        self::assertTrue($instance->hasKeys('string'));
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            [...$instance]
        );
    }

    #[Test]
    public function it_fails_to_instantiate_with_an_item_containing_already_parameters(): void
    {
        $this->expectException(InvalidArgument::class);

        Parameters::fromAssociative([
            'foo' => Item::fromAssociative(
                true,
                Parameters::fromAssociative(['bar' => false])
            ),
        ]);
    }

    #[Test]
    #[DataProvider('invalidMapKeyProvider')]
    public function it_fails_to_instantiate_with_a_parameter_and_an_invalid_key(string|int $key): void
    {
        $this->expectException(SyntaxError::class);

        Parameters::fromAssociative([$key => Item::true()]); // @phpstan-ignore-line
    }

    /**
     * @return iterable<string,array{key:string|int}>
     */
    public static function invalidMapKeyProvider(): iterable
    {
        yield 'key is an integer' => ['key' => 42];
        yield 'key start with an integer' => ['key' => '1b'];
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::fromString('helloWorld');
        $booleanItem = Item::true();
        $instance = Parameters::fromAssociative(['string' => 'helloWorld', 'boolean' => true]);

        self::assertCount(2, [...$instance->toAssociative()]);
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            [...$instance]
        );

        $deletedInstance = $instance->removeByKeys('boolean');
        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->hasKeys('boolean'));
        self::assertFalse($deletedInstance->hasIndices(1));

        $instance = new class () implements StructuredFieldProvider {
            public function toStructuredField(): StructuredField
            {
                return Item::fromString('BarBaz');
            }
        };

        $addedInstance = $deletedInstance->append('foobar', $instance);
        self::assertSame($addedInstance, $addedInstance->append('foobar', Item::new('BarBaz')));
        self::assertTrue($addedInstance->hasIndices(1));
        self::assertFalse($addedInstance->hasIndices(3, 23));
        self::assertFalse($addedInstance->hasIndices());

        $foundItem = $addedInstance->getByIndex(1);

        self::assertCount(2, $addedInstance);
        self::assertIsString($foundItem[1]->value());
        self::assertStringContainsString('BarBaz', $foundItem[1]->value());

        $altInstance = $addedInstance->removeByKeys('foobar', 'string');

        self::assertCount(0, $altInstance);
        self::assertFalse($altInstance->isNotEmpty());
        self::assertTrue($altInstance->isEmpty());
    }

    #[Test]
    public function it_returns_the_same_object_if_no_member_is_removed(): void
    {
        $stringItem = Item::new('helloWorld');
        $booleanItem = Item::true();
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Parameters::fromAssociative($arrayParams);

        self::assertSame($instance, $instance->removeByKeys('foo', 'bar', 'baz'));
    }

    #[Test]
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        Parameters::fromAssociative(['bébé' => Item::false()]);
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = Parameters::new();

        self::assertFalse($instance->hasKeys('foobar', 'barbaz'));
        self::assertFalse($instance->hasKeys());

        $instance->getByKey('foobar');
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = Parameters::new();

        self::assertFalse($instance->hasIndices(3));

        $this->expectException(InvalidOffset::class);

        $instance->getByIndex(3);
    }

    #[Test]
    public function it_can_prepend_a_new_member(): void
    {
        $instance = Parameters::new()
            ->append('a', Item::false())
            ->prepend('b', Item::true());

        self::assertSame(';b;a=?0', $instance->toHttpValue());
        self::assertSame(';b;a=?0', (string) $instance);
    }

    #[Test]
    public function it_can_push_and_unshift_new_pair(): void
    {
        $instance = Parameters::new()
            ->push(['a', false])
            ->unshift(['b', true]);

        self::assertSame(';b;a=?0', $instance->toHttpValue());
        self::assertSame(';b;a=?0', (string) $instance);
    }

    #[Test]
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        Parameters::new()->insert(3, ['a', 1]);
    }

    #[Test]
    public function it_can_returns_the_container_member_keys_with_pairs(): void
    {
        $instance = Parameters::new();

        self::assertSame([], $instance->keys());
        self::assertSame(['a', 'b'], $instance->push(['a', false], ['b', true])->keys());

        $container = Parameters::new()
            ->unshift(['a', '42'])
            ->push(['b', 42])
            ->insert(1, ['c', 42.0])
            ->replace(0, ['d', 'forty-two']);

        self::assertSame(['d', 'c', 'b'], $container->keys());
        self::assertSame(';d="forty-two";c=42.0;b=42', $container->toHttpValue());
    }

    #[Test]
    public function it_can_push_nothing(): void
    {
        self::assertEquals(Parameters::new()->push()->unshift(), Parameters::new());
    }

    #[Test]
    public function it_fails_to_replace_unknown_index(): void
    {
        $this->expectException(InvalidOffset::class);

        Parameters::new()->replace(0, ['a', true]);
    }

    #[Test]
    public function it_returns_the_same_instance_if_nothing_is_replaced(): void
    {
        $field = Parameters::new()->push(['a', true]);

        self::assertSame($field->replace(0, ['a', true]), $field);
    }

    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = Parameters::new();

        self::assertSame([], $instance->keys());

        $newInstance = Parameters::new()
            ->append('a', Item::false())
            ->prepend('b', Item::true());

        self::assertSame(['b', 'a'], $newInstance->keys());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => false]);
        $instance2 = Parameters::fromAssociative(['b' => true]);
        $instance3 = Parameters::fromAssociative(['a' => 42]);

        $instance4 = $instance1->mergeAssociative($instance2, $instance3);

        self::assertEquals(Item::fromInteger(42), $instance4->getByKey('a'));
        self::assertEquals(Item::true(), $instance4->getByKey('b'));
    }

    #[Test]
    public function it_can_merge_two_or_more_dictionaries_different_result(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => Item::false()]);
        $instance2 = Parameters::fromAssociative(['b' => Item::true()]);
        $instance3 = Parameters::fromAssociative(['a' => Item::fromInteger(42)]);

        $instance4 = $instance3->mergeAssociative($instance2, $instance1);

        self::assertEquals(Item::false(), $instance4->getByKey('a'));
        self::assertEquals(Item::true(), $instance4->getByKey('b'));
    }

    #[Test]
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = Parameters::fromAssociative(['a' => Item::false()]);

        self::assertCount(1, $instance->mergeAssociative());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances_using_pairs(): void
    {
        $instance1 = Parameters::fromPairs([['a', Item::false()]]);
        $instance2 = Parameters::fromPairs([['b', Item::true()]]);
        $instance3 = Parameters::fromPairs([['a', Item::new(42)]]);

        $instance4 = $instance3->mergePairs($instance2, $instance1);

        self::assertCount(2, $instance4);
        self::assertEquals(Item::false(), $instance4->getByKey('a'));
        self::assertEquals(Item::true(), $instance4->getByKey('b'));
    }

    #[Test]
    public function it_can_merge_without_pairs_and_not_throw(): void
    {
        self::assertCount(1, Parameters::fromAssociative(['a' => Item::false()])->mergePairs());
    }

    #[Test]
    public function it_can_merge_dictionary_instances_via_pairs_or_associative(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => Item::false()]);
        $instance2 = Parameters::fromAssociative(['b' => Item::true()]);

        $instance3 = clone $instance1;
        $instance4 = clone $instance2;

        self::assertEquals($instance3->mergeAssociative($instance4), $instance1->mergePairs($instance2));
    }

    #[Test]
    public function it_can_return_bare_items_values(): void
    {
        $instance = Parameters::fromAssociative([
            'string' => Item::fromString('helloWorld'),
            'boolean' => Item::true(),
        ]);

        self::assertSame('helloWorld', $instance->getByKey('string')->value());
    }

    #[Test]
    public function it_fails_to_parse_invalid_parameters_pairs(): void
    {
        $this->expectException(SyntaxError::class);

        Parameters::fromHttpValue(';foo =  bar');
    }

    #[Test]
    public function it_successfully_parse_a_parameter_value_with_optional_white_spaces_in_front(): void
    {
        self::assertEquals(
            Parameters::fromHttpValue(';foo=bar'),
            Parameters::fromHttpValue('        ;foo=bar')
        );
    }

    #[Test]
    public function it_can_delete_a_member_via_array_access(): void
    {
        $instance = Parameters::new()->add('foo', 'bar');

        self::assertTrue($instance->isNotEmpty());
        self::assertSame($instance->removeByIndices(), $instance);
        self::assertFalse($instance->removeByKeys('foo')->isNotEmpty());

        $instanceWithoutMembers = Parameters::new();
        self::assertSame($instanceWithoutMembers->removeByKeys(), $instanceWithoutMembers);
    }

    #[Test]
    public function it_throws_if_the_structured_field_is_not_supported(): void
    {
        $this->expectException(InvalidArgument::class);

        Parameters::fromPairs([['foo', InnerList::new(42)]]);
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $token = Token::fromString('token');

        $structuredField = Parameters::fromPairs([
            ['foobar', 'foobar'],
            ['zero', 0],
            ['false', false],
            ['token', $token],
        ]);

        self::assertSame($structuredField->getByKey('false'), $structuredField['false']);

        self::assertFalse($structuredField->getByKey('false')->value());
        self::assertFalse($structuredField['false']->value());
        self::assertFalse(isset($structuredField['toto']));
    }

    #[Test]
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(Parameters::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar']);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        Parameters::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar'] = Item::false();
    }

    #[Test]
    public function it_can_detect_the_member_keys_and_indices(): void
    {
        $instance = Parameters::new()
            ->append('a', Item::false())
            ->prepend('b', Item::true())
            ->push(['c', Item::fromToken('blablabla')]);

        self::assertSame(2, $instance->indexByKey('c'));
        self::assertSame(0, $instance->indexByKey('b'));
        self::assertNull($instance->indexByKey('foobar'));
        self::assertSame('c', $instance->keyByIndex(-1));
        self::assertNull($instance->keyByIndex(23));
    }
}
