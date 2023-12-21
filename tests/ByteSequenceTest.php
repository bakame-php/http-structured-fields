<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\Attributes\Test;

final class ByteSequenceTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'binary.json',
    ];

    #[Test]
    public function it_will_fail_on_invalid_decoded_string_with_inner_space(): void
    {
        $this->expectException(SyntaxError::class);

        ByteSequence::fromEncoded('a a');
    }

    #[Test]
    public function it_will_return_null_on_invalid_encoded_string(): void
    {
        self::assertNull(ByteSequence::tryFromEncoded('a a'));
        self::assertNull(ByteSequence::tryFromEncoded('aaaaa'));
    }

    #[Test]
    public function it_will_fail_on_invalid_decoded_string(): void
    {
        $this->expectException(SyntaxError::class);

        ByteSequence::fromEncoded('aaaaa');
    }

    #[Test]
    public function it_can_decode_base64_field(): void
    {
        $encoded = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value = ByteSequence::fromEncoded($encoded);

        self::assertSame('pretend this is binary content.', $value->decoded());
        self::assertSame($encoded, $value->encoded());
    }

    #[Test]
    public function it_can_encode_raw_field(): void
    {
        $decoded = 'pretend this is binary content.';
        $encoded = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value = ByteSequence::fromDecoded($decoded);

        self::assertSame('pretend this is binary content.', $value->decoded());
        self::assertSame($encoded, $value->encoded());
    }

    #[Test]
    public function it_can_compare_instances(): void
    {
        $decoded = 'pretend this is binary content.';
        $encoded = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value = ByteSequence::fromDecoded($decoded);

        self::assertTrue($value->equals(ByteSequence::fromEncoded($encoded)));
        self::assertFalse($value->equals(ByteSequence::fromDecoded($encoded)));
    }
}
