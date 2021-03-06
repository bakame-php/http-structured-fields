<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\ByteSequence
 */
final class ByteSequenceTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/binary.json',
    ];

    /**
     * @test
     */
    public function it_will_fail_on_invalid_decoded_string(): void
    {
        $this->expectException(SyntaxError::class);

        ByteSequence::fromEncoded('a a');
    }

    /**
     * @test
     */
    public function it_can_decode_base64_field(): void
    {
        $source = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $item = ByteSequence::fromEncoded($source);

        self::assertSame('pretend this is binary content.', $item->decoded());
        self::assertSame($source, $item->encoded());
        self::assertSame(":$source:", $item->toHttpValue());
    }

    /**
     * @test
     */
    public function it_can_encode_raw_field(): void
    {
        $decoded = 'pretend this is binary content.';
        $source = 'cHJldGVuZCB0aGlzIGlzIGJpbmFyeSBjb250ZW50Lg==';
        $item = ByteSequence::fromDecoded($decoded);

        self::assertSame('pretend this is binary content.', $item->decoded());
        self::assertSame($source, $item->encoded());
        self::assertSame(":$source:", $item->toHttpValue());
    }
}
