<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function implode;

abstract class StructuredFieldTestCase extends TestCase
{
    /** @var array<string> */
    protected static array $paths;

    #[Test]
    #[DataProvider('httpWgDataProvider')]
    public function it_can_pass_http_wg_tests(TestRecord $test): void
    {
        if ($test->mustFail) {
            $this->expectException(SyntaxError::class);
        }

        $structuredField = TestDataType::from($test->type)->newStructuredField(implode(',', $test->raw));

        if (!$test->mustFail) {
            self::assertSame(implode(',', $test->canonical), $structuredField->toHttpValue());
        }
    }

    /**
     * @throws JsonException
     * @return iterable<string, array<TestRecord>>
     */
    public static function httpWgDataProvider(): iterable
    {
        foreach (static::$paths as $path) {
            foreach (TestRecordCollection::fromPath($path) as $test) {
                yield $test->name => [$test];
            }
        }
    }
}
