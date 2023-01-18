<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\TestCase;
use function iterator_to_array;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\InnerList
 */
final class InnerListTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = InnerList::fromList($arrayParams, Parameters::fromAssociative(['test' => Item::from(42)]));

        self::assertSame($stringItem, $instance->get(0));
        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->hasNoMembers());
        self::assertTrue($instance->parameters()->hasMembers());
        self::assertEquals($arrayParams, iterator_to_array($instance));

        $instance->clear();

        self::assertFalse($instance->hasMembers());
        self::assertTrue($instance->hasNoMembers());
        self::assertTrue($instance->parameters()->hasMembers());
    }

    /** @test */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = InnerList::fromList($arrayParams);

        self::assertCount(2, $instance);
        self::assertTrue($instance->has(1));
        self::assertFalse($instance->parameters()->hasMembers());

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->has(1));

        $instance->push('BarBaz');
        $instance->insert(1);
        $member = $instance->get(1);

        self::assertCount(2, $instance);
        self::assertIsString($member->value());
        self::assertStringContainsString('BarBaz', $member->value());

        $instance->remove(0, 1);

        self::assertCount(0, $instance);
        self::assertFalse($instance->hasMembers());
    }

    /** @test */
    public function it_can_unshift_insert_and_replace(): void
    {
        $container = InnerList::from();
        $container->unshift('42');
        $container->push(42);
        $container->insert(1, 42.0);
        $container->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertCount(3, $container);
        self::assertTrue($container->hasMembers());
        self::assertSame('(:SGVsbG8gV29ybGQ=: 42.0 42)', $container->toHttpValue());
    }

    /** @test */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        InnerList::from()->replace(0, ByteSequence::fromDecoded('Hello World'));
    }

    /** @test */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        InnerList::from()->insert(3, ByteSequence::fromDecoded('Hello World'));
    }

    /** @test */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = InnerList::from();

        self::assertFalse($instance->has(3));

        $this->expectException(InvalidOffset::class);

        $instance->get(3);
    }

    /** @test */
    public function it_can_access_its_parameter_values(): void
    {
        $instance = InnerList::fromList([false], ['foo' => 'bar']);

        self::assertSame('bar', $instance->parameters()['foo']->value());
    }

    /** @test */
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $this->expectException(StructuredFieldError::class);

        InnerList::fromList([false], ['foo' => 'bar'])->parameters()['bar']->value();
    }

    /** @test */
    public function it_successfully_parse_a_http_field(): void
    {
        $instance = InnerList::fromHttpValue('("hello)world" 42 42.0;john=doe);foo="bar("');

        self::assertCount(3, $instance);
        self::assertCount(1, $instance->parameters());
        self::assertSame('bar(', $instance->parameters()['foo']->value());
        self::assertSame('hello)world', $instance->get(0)->value());
        self::assertSame(42, $instance->get(1)->value());
        self::assertSame(42.0, $instance->get(2)->value());
        self::assertEquals(Token::fromString('doe'), $instance->get(2)->parameters()['john']->value());
    }

    /** @test */
    public function it_successfully_parse_a_http_field_with_optional_white_spaces_in_front(): void
    {
        self::assertEquals(
            InnerList::fromHttpValue('("hello)world" 42 42.0;john=doe);foo="bar("'),
            InnerList::fromHttpValue('        ("hello)world" 42 42.0;john=doe);foo="bar("')
        );
    }

    /** @test */
    public function it_implements_the_array_access_interface(): void
    {
        $sequence = InnerList::from();
        $sequence[] = 42;

        self::assertTrue(isset($sequence[0]));
        self::assertEquals(42, $sequence[0]->value());

        $sequence[0] = false;

        self::assertNotEquals(42, $sequence[0]->value());
        unset($sequence[0]);

        self::assertCount(0, $sequence);
    }

    /** @test */
    public function it_fails_to_insert_unknown_index_via_the_array_access_interface(): void
    {
        $this->expectException(StructuredFieldError::class);

        InnerList::from()[0] = Item::from(42.0);
    }

    /** @test */
    public function testArrayAccessThrowsInvalidIndex2(): void
    {
        $sequence = InnerList::from();
        unset($sequence[0]);

        self::assertCount(0, $sequence);
    }

    /** @test */
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(InvalidOffset::class);

        InnerList::from()->get('zero');
    }

    /** @test */
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $input = ['foobar', 0, false, $token];
        $structuredField = InnerList::fromList($input);

        self::assertFalse($structuredField[2]->value());
        self::assertEquals($token, $structuredField[-1]->value());
    }

    /** @test */
    public function it_can_create_via_with_parameters_method_a_new_object(): void
    {
        $instance1 = InnerList::fromList([Token::fromString('babayaga'), 'a', true], ['a' => true]);
        $instance2 = $instance1->withParameters(Parameters::fromAssociative(['a' => true]));
        $instance3 = $instance1->withParameters(Parameters::fromAssociative(['a' => false]));

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1->parameters(), $instance3->parameters());
        self::assertEquals(iterator_to_array($instance1), iterator_to_array($instance3));
    }

    /** @test */
    public function it_can_create_via_parameters_access_methods_a_new_object(): void
    {
        $instance1 = InnerList::fromList([Token::fromString('babayaga'), 'a', true], ['a' => true]);
        $instance2 = $instance1->appendParameter('a', true);
        $instance3 = $instance1->prependParameter('a', false);
        $instance4 = $instance1->withoutParameter('b');
        $instance5 = $instance1->withoutParameter('a');
        $instance6 = $instance1->clearParameters();

        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1->parameters(), $instance3->parameters());
        self::assertEquals(iterator_to_array($instance1), iterator_to_array($instance3));
        self::assertSame($instance1, $instance4);
        self::assertFalse($instance5->parameters()->hasMembers());
        self::assertTrue($instance6->parameters()->hasNoMembers());
    }
}
