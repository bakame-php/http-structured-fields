<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function iterator_to_array;

final class DictionaryTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/dictionary.json',
    ];

    /** @test */
    public function it_can_be_instantiated_with_an_collection_of_item_or_inner_list(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Dictionary::fromAssociative($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            iterator_to_array($instance->toPairs(), false)
        );

        self::assertEquals($arrayParams, iterator_to_array($instance));
        $instance->clear();
        self::assertFalse($instance->hasMembers());
    }

    /** @test */
    public function test_it_can_be_instantiated_with_key_value_pairs(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [['string', $stringItem], ['boolean', $booleanItem]];
        $instance = Dictionary::fromPairs($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue(isset($instance['string']));
        self::assertSame($stringItem, $instance['string']);
        self::assertTrue($instance->has('string'));
        self::assertEquals(
            [['string', $stringItem], ['boolean', $booleanItem]],
            iterator_to_array($instance->toPairs(), false)
        );
    }

    /** @test */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Dictionary::fromAssociative($arrayParams);

        self::assertCount(2, $instance);
        self::assertTrue($instance->hasMembers());
        $instance->delete('boolean');

        self::assertCount(1, $instance);
        self::assertFalse($instance->has('boolean'));
        self::assertFalse(isset($instance['boolean']));
        self::assertFalse($instance->hasPair(1));

        $instance->append('foobar', Item::from('BarBaz'));
        self::assertTrue($instance->hasPair(1));

        /** @var array{0:string, 1:Item} $foundItem */
        $foundItem = $instance->pair(1);

        self::assertIsString($foundItem[1]->value());
        self::assertStringContainsString('BarBaz', $foundItem[1]->value());

        $instance->delete('foobar', 'string');
        self::assertCount(0, $instance);
        self::assertFalse($instance->hasMembers());
    }

    /** @test */
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = Dictionary::fromAssociative();
        self::assertFalse($instance->has('foobar'));

        $instance->get('foobar');
    }

    /** @test */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = Dictionary::fromAssociative();
        self::assertFalse($instance->hasPair(3));

        $instance->pair(3);
    }

    /** @test */
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        Dictionary::fromAssociative(['bébé'=> Item::from(false)]);
    }

    /** @test */
    public function it_can_prepend_a_new_member(): void
    {
        $instance = Dictionary::fromAssociative();
        $instance->append('a', Item::from(false));
        $instance->prepend('b', Item::from(true));

        self::assertSame('b, a=?0', $instance->toHttpValue());
    }

    /** @test */
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = Dictionary::fromAssociative();
        self::assertSame([], $instance->keys());
        $instance->append('a', Item::from(false));
        $instance->prepend('b', Item::from(true));

        self::assertSame(['b', 'a'], $instance->keys());
    }

    /** @test */
    public function it_can_merge_one_or_more_instances_using_associative(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::from(true)]);
        $instance3 = Dictionary::fromAssociative(['a' => Item::from(42)]);

        $instance1->mergeAssociative($instance2, $instance3);
        self::assertCount(2, $instance1);

        self::assertEquals(Item::from(42), $instance1->get('a'));
        self::assertEquals(Item::from(true), $instance1->get('b'));
    }

    /** @test */
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::from(true)]);
        $instance3 = Dictionary::fromAssociative(['a' => Item::from(42)]);

        $instance3->mergeAssociative($instance2, $instance1);
        self::assertCount(2, $instance3);

        self::assertEquals(Item::from(false), $instance3->get('a'));
        self::assertEquals(Item::from(true), $instance3->get('b'));
    }

    /** @test */
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        $instance = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance->mergeAssociative();
        self::assertCount(1, $instance);
    }

    /** @test */
    public function it_can_merge_one_or_more_instances_using_pairs(): void
    {
        $instance1 = Dictionary::fromPairs([['a', Item::from(false)]]);
        $instance2 = Dictionary::fromPairs([['b', Item::from(true)]]);
        $instance3 = Dictionary::fromPairs([['a', Item::from(42)]]);

        $instance3->mergePairs($instance2, $instance1);
        self::assertCount(2, $instance3);

        self::assertEquals(Item::from(false), $instance3->get('a'));
        self::assertEquals(Item::from(true), $instance3->get('b'));
    }

    /** @test */
    public function it_can_merge_without_pairs_and_not_throw(): void
    {
        $instance = Dictionary::fromAssociative(['a' => Item::from(false)]);

        self::assertCount(1, $instance->mergePairs());
    }

    /** @test */
    public function it_can_merge_dictionary_instances_via_pairs_or_associative(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::from(true)]);

        $instance3 = clone $instance1;
        $instance4 = clone $instance2;

        self::assertEquals($instance3->mergeAssociative($instance4), $instance1->mergePairs($instance2));
    }

    /** @test */
    public function it_can_handle_string_with_comma(): void
    {
        $expected = 'a=foobar;test="bar, baz", b=toto';
        $instance = Dictionary::fromHttpValue($expected);

        self::assertSame($expected, $instance->toHttpValue());
        self::assertCount(2, $instance);
    }

    /** @test */
    public function it_fails_to_add_an_integer_via_array_access(): void
    {
        $this->expectException(StructuredFieldError::class);

        $structuredField = Dictionary::fromPairs();
        $structuredField[0] = 23; // @phpstan-ignore-line
    }

    /** @test */
    public function it_can_delete_a_member_via_array_access(): void
    {
        $structuredField = Dictionary::fromPairs();
        $structuredField['foo'] = 'bar';
        self::assertTrue($structuredField->hasMembers());

        unset($structuredField['foo']);

        self::assertFalse($structuredField->hasMembers());
    }

    /** @test */
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(StructuredFieldError::class);

        $structuredField = Dictionary::fromPairs();
        $structuredField->get(0);
    }

    /** @test */
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
}
