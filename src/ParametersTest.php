<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;
use PHPUnit\Framework\Attributes\Test;

final class ParametersTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $paths = [
        '/param-dict.json',
        '/param-list.json',
        '/param-listlist.json',
    ];

    #[Test]
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Parameters::fromAssociative($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertTrue($instance->has('string'));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));

        self::assertEquals($arrayParams, [...$instance]);
    }

    #[Test]
    public function test_it_can_be_instantiated_with_key_value_pairs(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [['string', $stringItem], ['boolean', $booleanItem]];
        $instance = Parameters::fromPairs($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            [...$instance->toPairs()]
        );
    }

    #[Test]
    public function it_fails_to_instantiate_with_an_item_containing_already_parameters(): void
    {
        $this->expectException(InvalidArgument::class);

        Parameters::fromAssociative([
            'foo' => Item::from(
                true,
                Parameters::fromAssociative(['bar' => Item::from(false)])
            ),
        ]);
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Parameters::fromAssociative($arrayParams);

        self::assertCount(2, $instance);
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            [...$instance->toPairs()]
        );


        $deletedInstance = $instance->remove('boolean');

        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->has('boolean'));
        self::assertFalse($deletedInstance->hasPair(1));

        $addedInstance = $deletedInstance->append('foobar', Item::from('BarBaz'));
        self::assertTrue($addedInstance->hasPair(1));
        $foundItem = $addedInstance->pair(1);

        self::assertCount(2, $addedInstance);
        self::assertIsString($foundItem[1]->value());
        self::assertStringContainsString('BarBaz', $foundItem[1]->value());

        $altInstance = $addedInstance->remove('foobar', 'string');
        self::assertCount(0, $altInstance);
        self::assertFalse($altInstance->hasMembers());
        self::assertTrue($altInstance->hasNoMembers());
    }

    #[Test]
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        Parameters::fromAssociative(['bébé'=> Item::from(false)]);
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = Parameters::create();
        self::assertFalse($instance->has('foobar'));

        $instance->get('foobar');
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = Parameters::create();

        self::assertFalse($instance->hasPair(3));

        $this->expectException(InvalidOffset::class);

        $instance->pair(3);
    }

    #[Test]
    public function it_can_prepend_a_new_member(): void
    {
        $instance = Parameters::create()
            ->append('a', Item::from(false))
            ->prepend('b', Item::from(true));

        self::assertSame(';b;a=?0', $instance->toHttpValue());
        self::assertSame(';b;a=?0', (string) $instance);
    }

    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = Parameters::create();

        self::assertSame([], $instance->keys());

        $newInstance = Parameters::create()
            ->append('a', Item::from(false))
            ->prepend('b', Item::from(true));

        self::assertSame(['b', 'a'], $newInstance->keys());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = Parameters::fromAssociative(['a' =>false]);
        $instance2 = Parameters::fromAssociative(['b' => true]);
        $instance3 = Parameters::fromAssociative(['a' => 42]);

        $instance4 = $instance1->mergeAssociative($instance2, $instance3);

        self::assertEquals(Item::from(42), $instance4->get('a'));
        self::assertEquals(Item::from(true), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_two_or_more_dictionaries_different_result(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Parameters::fromAssociative(['b' => Item::from(true)]);
        $instance3 = Parameters::fromAssociative(['a' => Item::from(42)]);

        $instance4 = $instance3->mergeAssociative($instance2, $instance1);

        self::assertEquals(Item::from(false), $instance4->get('a'));
        self::assertEquals(Item::from(true), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = Parameters::fromAssociative(['a' => Item::from(false)]);

        self::assertCount(1, $instance->mergeAssociative());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances_using_pairs(): void
    {
        $instance1 = Parameters::fromPairs([['a', Item::from(false)]]);
        $instance2 = Parameters::fromPairs([['b', Item::from(true)]]);
        $instance3 = Parameters::fromPairs([['a', Item::from(42)]]);

        $instance4 = $instance3->mergePairs($instance2, $instance1);

        self::assertCount(2, $instance4);
        self::assertEquals(Item::from(false), $instance4->get('a'));
        self::assertEquals(Item::from(true), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_without_pairs_and_not_throw(): void
    {
        self::assertCount(1, Parameters::fromAssociative(['a' => Item::from(false)])->mergePairs());
    }

    #[Test]
    public function it_can_merge_dictionary_instances_via_pairs_or_associative(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Parameters::fromAssociative(['b' => Item::from(true)]);

        $instance3 = clone $instance1;
        $instance4 = clone $instance2;

        self::assertEquals($instance3->mergeAssociative($instance4), $instance1->mergePairs($instance2));
    }

    #[Test]
    public function it_can_return_bare_items_values(): void
    {
        $instance = Parameters::fromAssociative([
            'string' => Item::from('helloWorld'),
            'boolean' => Item::from(true),
        ]);

        self::assertSame('helloWorld', $instance->get('string')->value());
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
        $instance = Parameters::create()->add('foo', 'bar');

        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->remove('foo')->hasMembers());
    }

    #[Test]
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(InvalidOffset::class);

        Parameters::create()->get(0);
    }

    #[Test]
    public function it_throws_if_the_structured_field_is_not_supported(): void
    {
        $this->expectException(InvalidArgument::class);

        Parameters::fromPairs([['foo', InnerList::from(42)]]);
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

        unset(Parameters::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar']);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        Parameters::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar'] = Item::from(false);
    }
}
