<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DataTypeTest extends TestCase
{
    #[Test]
    public function ti_will_generate_the_structured_field_text_represenation_for_an_innerlist(): void
    {
        self::assertSame(
            DataType::InnerList->serialize([['a', 'b', 'c'], [['a', true]]]),
            InnerList::fromPair([['a', 'b', 'c'], [['a', true]]])->toHttpValue()
        );
    }

    #[Test]
    public function ti_will_generate_the_structured_field_text_represenation_for_an_item(): void
    {
        self::assertSame(
            DataType::Item->serialize(['coucoulesamis', [['a', false]]]),
            Item::fromPair(['coucoulesamis', [['a', false]]])->toHttpValue()
        );
    }

    #[Test]
    public function ti_will_generate_the_structured_field_text_represenation_for_parameters(): void
    {
        $data = [['a', false], ['b', true]];

        self::assertSame(
            DataType::Parameters->serialize($data),
            Parameters::fromPairs($data)->toHttpValue()
        );
    }

    #[Test]
    public function ti_will_generate_the_structured_field_text_represenation_for_dictionary(): void
    {
        $data = [['a', false], ['b', Item::fromDateString('+30 minutes')]];

        self::assertSame(
            DataType::Dictionary->serialize($data),
            Dictionary::fromPairs($data)->toHttpValue()
        );
    }

    #[Test]
    public function it_will_generate_the_structured_field_text_represenation_for_list(): void
    {
        $data = [
            ['coucoulesamis', [['a', false]]],
            [['a', 'b', Item::fromDateString('+30 minutes')], [['a', true]]],
        ];

        self::assertSame(
            DataType::List->serialize($data),
            OuterList::fromPairs($data)->toHttpValue() /* @phpstan-ignore-line */
        );
    }

    #[Test]
    public function it_will_build_the_structured_fields_from_pairs(): void
    {
        $field = DataType::Dictionary->serialize([
            ['a',
                [
                    [
                        [1, []],
                        [2, []],
                    ],
                    [],
                ],
            ],
        ]);

        self::assertSame('a=(1 2)', $field);
    }

    #[Test]
    public function it_will_build_the_structured_fields_from_simplified_item(): void
    {
        $field = DataType::Dictionary->serialize([
            ['a',
                [
                    [1, 2],
                    [],
                ],
            ],
        ]);

        self::assertSame('a=(1 2)', $field);
    }
}
