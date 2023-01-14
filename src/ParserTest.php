<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;

final class ParserTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/examples.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/key-generated.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/large-generated.json',
    ];

    /** @test */
    public function it_will_fail_with_wrong_boolean(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('a=1, b=?3;foo=9, c=3');
    }

    /** @test */
    public function it_will_fail_with_wrong_number(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('key=-1ab');
    }

    /** @test */
    public function it_will_fail_with_wrong_sequence(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('a=:toto89é:');
    }

    /** @test */
    public function it_parse_a_date_item(): void
    {
        $field = Parser::parseDictionary('a=@12345678;key=1');

        self::assertInstanceOf(Item::class, $field['a']);
        self::assertInstanceOf(DateTimeImmutable::class, $field['a']->value());
    }

    /** @test */
    public function it_will_fail_with_wrong_date_format(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('a=@12345.678');
    }

    /** @test */
    public function it_will_fail_with_out_of_range_date_format(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('a=@'. 1_000_000_000_000_000);
    }

    /** @test */
    public function it_will_fail_with_wrong_string(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('a="foo \O bar"');
    }

    /** @test */
    public function it_will_fail_with_wrong_string_utf8(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('a="foébar"');
    }

    /** @test */
    public function it_fails_to_parse_invalid_string_1(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseList('(foo;number="hello\")');
    }

    /** @test */
    public function it_fails_to_parse_invalid_string_2(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseDictionary('number="hell\o"');
    }

    /** @test */
    public function it_fails_to_parse_an_invalid_http_field(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseInnerList('("hello)world" 42 42.0;john=doe);foo="bar(" toto');
    }

    /** @test */
    public function it_fails_to_parse_an_invalid_http_field_2(): void
    {
        $this->expectException(SyntaxError::class);

        Parser::parseInnerList('"hello)world" 42 42.0;john=doe);foo="bar("');
    }
}
