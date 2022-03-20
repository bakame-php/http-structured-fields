<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function iterator_to_array;
use function var_export;

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
        $this->expectException(SyntaxError::class);

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

        Parameters::fromAssociative(['bébé'=> Item::from(false)]);
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

        $instance1->merge($instance2, $instance3);

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

        $instance3->merge($instance2, $instance1);

        self::assertEquals(Item::from(false), $instance3->get('a'));
        self::assertEquals(Item::from(true), $instance3->get('b'));
    }

    /**
     * @test
     */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = Parameters::fromAssociative(['a' => Item::from(false)]);
        $instance->merge();
        self::assertCount(1, $instance);
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
    public function it_can_be_regenerated_with_eval(): void
    {
        $instance = Parameters::fromAssociative(['a' => Item::from(false)]);

        /** @var Parameters $generatedInstance */
        $generatedInstance = eval('return '.var_export($instance, true).';');

        self::assertEquals($instance, $generatedInstance);
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
}
