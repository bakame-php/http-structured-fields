<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\Attributes\Test;

final class DisplayStringTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'display-string.json',
    ];

    #[Test]
    public function it_will_fail_on_invalid_decoded_string_with_invalid_utf8(): void
    {
        $this->expectException(SyntaxError::class);

        DisplayString::fromEncoded('a %a');
    }

    #[Test]
    public function it_will_fail_on_invalid_decoded_string(): void
    {
        $this->expectException(SyntaxError::class);

        DisplayString::fromEncoded('%c3%28"');
    }

    #[Test]
    public function it_can_decode_base64_field(): void
    {
        $encoded = 'foo %22bar%22 \ baz';
        $value = DisplayString::fromEncoded($encoded);

        self::assertSame('foo "bar" \ baz', $value->decoded());
        self::assertSame($encoded, $value->encoded());
    }

    #[Test]
    public function it_can_encode_raw_field(): void
    {
        $decoded = 'füü';
        $encoded = 'f%c3%bc%c3%bc';
        $value = DisplayString::fromDecoded($decoded);

        self::assertSame($decoded, $value->decoded());
        self::assertSame($encoded, $value->encoded());
    }

    #[Test]
    public function it_can_compare_instances(): void
    {
        $decoded = 'füü';
        $encoded = 'f%c3%bc%c3%bc';

        self::assertTrue(DisplayString::fromEncoded($encoded)->equals(DisplayString::fromEncoded($encoded)));
        self::assertTrue(DisplayString::fromDecoded($decoded)->equals(DisplayString::fromDecoded($decoded)));
        self::assertTrue(DisplayString::fromEncoded($encoded)->equals(DisplayString::fromDecoded($decoded)));
    }
}
