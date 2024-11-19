<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\Attributes\Test;

final class BytesTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'binary.json',
    ];

    #[Test]
    public function it_will_fail_on_invalid_decoded_string_with_inner_space(): void
    {
        $this->expectException(SyntaxError::class);

        Bytes::fromEncoded('a a');
    }

    #[Test]
    public function it_will_return_null_on_invalid_encoded_string(): void
    {
        self::assertNull(Bytes::tryFromEncoded('a a'));
        self::assertNull(Bytes::tryFromEncoded('aaaaa'));
    }

    #[Test]
    public function it_will_fail_on_invalid_decoded_string(): void
    {
        $this->expectException(SyntaxError::class);

        Bytes::fromEncoded('aaaaa');
    }

    #[Test]
    public function it_can_decode_base64_field(): void
    {
        $encoded = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value = Bytes::fromEncoded($encoded);

        self::assertSame('pretend this is binary content.', $value->decoded());
        self::assertSame($encoded, $value->encoded());
    }

    #[Test]
    public function it_can_encode_raw_field(): void
    {
        $decoded = 'pretend this is binary content.';
        $encoded = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value = Bytes::fromDecoded($decoded);

        self::assertSame('pretend this is binary content.', $value->decoded());
        self::assertSame($encoded, $value->encoded());
    }

    #[Test]
    public function it_can_compare_instances(): void
    {
        $decoded = 'pretend this is binary content.';
        $encoded = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value = Bytes::fromDecoded($decoded);

        self::assertTrue($value->equals(Bytes::fromEncoded($encoded)));
        self::assertFalse($value->equals(Bytes::fromDecoded($encoded)));
    }
}
