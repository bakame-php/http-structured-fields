<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\Attributes\Test;

final class ByteSequenceTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $paths = [
        '/binary.json',
    ];

    #[Test]
    public function it_will_fail_on_invalid_decoded_string_with_inner_space(): void
    {
        $this->expectException(SyntaxError::class);

        ByteSequence::fromEncoded('a a');
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
        $source = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $item = ByteSequence::fromEncoded($source);

        self::assertSame('pretend this is binary content.', $item->decoded());
        self::assertSame($source, $item->encoded());
    }

    #[Test]
    public function it_can_encode_raw_field(): void
    {
        $decoded = 'pretend this is binary content.';
        $source = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $item = ByteSequence::fromDecoded($decoded);

        self::assertSame('pretend this is binary content.', $item->decoded());
        self::assertSame($source, $item->encoded());
    }

    #[Test]
    public function it_can_compare_instances(): void
    {
        $decoded = 'pretend this is binary content.';
        $source = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $value1 = ByteSequence::fromDecoded($decoded);
        $value2 = ByteSequence::fromEncoded($source);
        $value3 = ByteSequence::fromDecoded($source);

        self::assertTrue($value1->equals($value2));
        self::assertFalse($value1->equals($value3));
    }
}
