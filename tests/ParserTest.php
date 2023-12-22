<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * @phpstan-import-type SfType from StructuredField
 */
final class ParserTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'examples.json',
        'key-generated.json',
        'large-generated.json',
    ];

    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new Parser();
    }

    #[Test]
    public function it_will_fail_with_wrong_boolean(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a=1, b=?3;foo=9, c=3');
    }

    #[Test]
    public function it_will_fail_with_wrong_number(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('key=-1ab');
    }

    #[Test]
    public function it_will_fail_with_wrong_sequence(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a=:toto89é:');
    }

    #[Test]
    public function it_parse_a_date_item(): void
    {
        $field = $this->parser->parseDictionary('a=@12345678;key=1');

        self::assertInstanceOf(DateTimeImmutable::class, $field['a'][0]);
        self::assertSame(1, $field['a'][1]['key']);
    }

    #[Test]
    public function it_will_fail_with_wrong_date_format(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a=@12345.678');
    }

    #[Test]
    public function it_will_fail_with_out_of_range_date_format(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a=@'. 1_000_000_000_000_000);
    }

    #[Test]
    public function it_will_fail_with_wrong_string(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a="foo \O bar"');
    }

    #[Test]
    public function it_will_fail_with_wrong_string_utf8(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a="foébar"');
    }

    #[Test]
    public function it_will_fail_with_wrong_string_encoded_char(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a=%"foobar'.rawurlencode(chr(10)).'"');
    }

    #[Test]
    public function it_will_fail_with_wrong_detail_string_utf8(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('a=%"foébar"');
    }

    #[Test]
    public function it_fails_to_parse_invalid_string_1(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseList('(foo;number="hello\")');
    }

    #[Test]
    public function it_fails_to_parse_invalid_string_2(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseDictionary('number="hell\o"');
    }

    #[Test]
    public function it_fails_to_parse_an_invalid_http_field(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseInnerList('("hello)world" 42 42.0;john=doe);foo="bar(" toto');
    }

    #[Test]
    public function it_fails_to_parse_an_invalid_http_field_2(): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseInnerList('"hello)world" 42 42.0;john=doe);foo="bar("');
    }

    #[Test]
    #[DataProvider('provideHttpValueForDataType')]
    public function it_parses_basic_data_type(string $httpValue, ByteSequence|Token|DateTimeImmutable|string|int|float|bool $expected): void
    {
        $field = $this->parser->parseValue($httpValue);
        if (is_scalar($expected)) {
            self::assertSame($expected, $field);
        } else {
            self::assertEquals($expected, $field);
        }
    }

    /**
     * @return iterable<array{httpValue:string, expected:SfType}>
     */
    public static function provideHttpValueForDataType(): iterable
    {
        yield 'it parses a string' => [
            'httpValue' => '"hello world!"',
            'expected' => 'hello world!',
        ];

        yield 'it parses a float' => [
            'httpValue' => '1.23',
            'expected' => 1.23,
        ];

        yield 'it parses an integer' => [
            'httpValue' => '23',
            'expected' => 23,
        ];

        yield 'it parses an byte sequence' => [
            'httpValue' => ':cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==:',
            'expected' => ByteSequence::fromEncoded('cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg=='),
        ];

        yield 'it parses an token' => [
            'httpValue' => 'text/csv',
            'expected' => Token::fromString('text/csv'),
        ];

        yield 'it parses an date' => [
            'httpValue' => '@1234567890',
            'expected' => new DateTimeImmutable('@1234567890'),
        ];

        yield 'it parses true' => [
            'httpValue' => '?1',
            'expected' => true,
        ];

        yield 'it parses false' => [
            'httpValue' => '?0',
            'expected' => false,
        ];
    }

    #[Test]
    #[DataProvider('provideInvalidHttpValueForDataType')]
    public function it_fails_to_parse_basic_data_type(string $httpValue): void
    {
        $this->expectException(SyntaxError::class);

        $this->parser->parseValue($httpValue);
    }

    /**
     * @return array<array<string>>
     */
    public static function provideInvalidHttpValueForDataType(): array
    {
        return [
            ['!invalid'],
            ['"inva'."\n".'lid"'],
            ['@1_000_000_000_000.0'],
            ['-1_000_000_000_000.0'],
            ['      '],
        ];
    }
}
