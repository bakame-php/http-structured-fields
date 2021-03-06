<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function iterator_to_array;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\Parameters
 */
final class ParametersTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/param-dict.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/param-list.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/param-listlist.json',
    ];

    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Parameters::fromAssociative($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
        $instance->clear();
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
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
            iterator_to_array($instance->toPairs(), false)
        );
    }

    /**
     * @test
     */
    public function it_fails_to_instantiate_with_an_item_containing_already_parameters(): void
    {
        $this->expectException(ForbiddenStateError::class);

        Parameters::fromAssociative([
            'foo' => Item::from(
                true,
                Parameters::fromAssociative(['bar' => Item::from(false)])
            ),
        ]);
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Parameters::fromAssociative($arrayParams);

        self::assertCount(2, $instance);
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            iterator_to_array($instance->toPairs(), false)
        );


        $instance->delete('boolean');

        self::assertCount(1, $instance);
        self::assertFalse($instance->has('boolean'));
        self::assertFalse($instance->hasPair(1));

        $instance->append('foobar', Item::from('BarBaz'));
        self::assertTrue($instance->hasPair(1));
        $foundItem = $instance->pair(1);

        self::assertCount(2, $instance);
        self::assertIsString($foundItem[1]->value);
        self::assertStringContainsString('BarBaz', $foundItem[1]->value);

        $instance->delete('foobar', 'string');
        self::assertCount(0, $instance);
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        Parameters::fromAssociative(['b??b??'=> Item::from(false)]);
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = Parameters::fromAssociative();
        self::assertFalse($instance->has('foobar'));

        $instance->get('foobar');
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = Parameters::fromAssociative();
        self::assertFalse($instance->hasPair(3));

        $instance->pair(3);
    }

    /**
     * @test
     */
    public function it_can_prepend_a_new_member(): void
    {
        $instance = Parameters::fromAssociative();
        $instance->append('a', Item::from(false));
        $instance->prepend('b', Item::from(true));

        self::assertSame(';b;a=?0', $instance->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = Parameters::fromAssociative();
        self::assertSame([], $instance->keys());
        $instance->append('a', Item::from(false));
        $instance->prepend('b', Item::from(true));

        self::assertSame(['b', 'a'], $instance->keys());
    }

    /**
     * @test
     */
    public function it_can_merge_one_or_more_instances(): void
    {
        $instance1 = Parameters::fromAssociative(['a' =>false]);
        $instance2 = Parameters::fromAssociative(['b' => true]);
        $instance3 = Parameters::fromAssociative(['a' => 42]);

        $instance1->mergeAssociative($instance2, $instance3);

        self::assertEquals(Item::from(42), $instance1->get('a'));
        self::assertEquals(Item::from(true), $instance1->get('b'));
    }

    /**
     * @test
     */
    public function it_can_merge_two_or_more_dictionaries_different_result(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Parameters::fromAssociative(['b' => Item::from(true)]);
        $instance3 = Parameters::fromAssociative(['a' => Item::from(42)]);

        $instance3->mergeAssociative($instance2, $instance1);

        self::assertEquals(Item::from(false), $instance3->get('a'));
        self::assertEquals(Item::from(true), $instance3->get('b'));
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = Parameters::fromAssociative(['a' => Item::from(false)]);
        $instance->mergeAssociative();
        self::assertCount(1, $instance);
    }

    /**
     * @test
     */
    public function it_can_merge_one_or_more_instances_using_pairs(): void
    {
        $instance1 = Parameters::fromPairs([['a', Item::from(false)]]);
        $instance2 = Parameters::fromPairs([['b', Item::from(true)]]);
        $instance3 = Parameters::fromPairs([['a', Item::from(42)]]);

        $instance3->mergePairs($instance2, $instance1);
        self::assertCount(2, $instance3);

        self::assertEquals(Item::from(false), $instance3->get('a'));
        self::assertEquals(Item::from(true), $instance3->get('b'));
    }

    /**
     * @test
     */
    public function it_can_merge_without_pairs_and_not_throw(): void
    {
        $instance = Parameters::fromAssociative(['a' => Item::from(false)]);

        self::assertCount(1, $instance->mergePairs());
    }

    /**
     * @test
     */
    public function it_can_merge_dictionary_instances_via_pairs_or_associative(): void
    {
        $instance1 = Parameters::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Parameters::fromAssociative(['b' => Item::from(true)]);

        $instance3 = clone $instance1;
        $instance4 = clone $instance2;

        self::assertEquals($instance3->mergeAssociative($instance4), $instance1->mergePairs($instance2));
    }

    /**
     * @test
     */
    public function it_fails_if_internal_parameters_are_changed_illegally_1(): void
    {
        $this->expectException(ForbiddenStateError::class);

        $fields = Item::from('/terms', ['rel' => 'copyright', 'anchor' => '#foo']);
        $fields->parameters->get('anchor')->parameters->set('yolo', 42);
        $fields->toHttpValue();
    }

    /**
     * @test
     */
    public function it_fails_if_internal_parameters_are_changed_illegally_2(): void
    {
        $this->expectException(ForbiddenStateError::class);

        $fields = Item::from('/terms', ['rel' => 'copyright', 'anchor' => '#foo']);
        $fields->parameters->get('anchor')->parameters->set('yolo', 42);
        $fields->parameters->get('anchor');
    }

    /**
     * @test
     */
    public function it_fails_if_internal_parameters_are_changed_illegally_3(): void
    {
        $this->expectException(ForbiddenStateError::class);

        $fields = Item::from('/terms', ['rel' => 'copyright', 'anchor' => '#foo']);
        $fields->parameters->get('anchor')->parameters->set('yolo', 42);
        $fields->parameters->value('anchor');
    }

    /**
     * @test
     */
    public function it_can_return_bare_items_values(): void
    {
        $instance = Parameters::fromAssociative([
            'string' => Item::from('helloWorld'),
            'boolean' => Item::from(true),
        ]);

        self::assertSame('helloWorld', $instance->value('string'));
        self::assertSame(['string' => 'helloWorld', 'boolean' => true], $instance->values());
    }

    /**
     * @test
     */
    public function it_fails_to_parse_invalid_parameters_pairs(): void
    {
        $this->expectException(SyntaxError::class);

        Parameters::fromHttpValue(';foo =  bar');
    }

    /**
     * @test
     */
    public function it_successfully_parse_a_parameter_value_with_optional_white_spaces_in_front(): void
    {
        self::assertEquals(
            Parameters::fromHttpValue(';foo=bar'),
            Parameters::fromHttpValue('        ;foo=bar')
        );
    }

    /**
     * @test
     */
    public function it_fails_creating_an_object_with_an_already_defined_parameter_configuration(): void
    {
        $this->expectException(StructuredFieldError::class);

        $parameters = Parameters::fromAssociative(['a' => false]);
        $item = $parameters->get('a');
        $item->parameters->set('b', true);
        self::assertSame('?0;b', $item->toHttpValue());

        $parameters->toHttpValue();
    }

    /**
     * @test
     */
    public function it_can_serialize_after_sanitizing_the_parameters(): void
    {
        $parameters = Parameters::fromAssociative(['a' => false]);
        $item = $parameters->get('a');
        $item->parameters->set('b', true);

        self::assertSame(';a=?0', $parameters->sanitize()->toHttpValue());
    }
}
