<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use JsonException;
use PHPUnit\Framework\TestCase;

abstract class StructuredFieldTest extends TestCase
{
    /** @var array|string[] */
    protected array $paths;

    /**
     * @dataProvider httpWgDataProvider
     * @test
     */
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
    public function httpWgDataProvider(): iterable
    {
        foreach ($this->paths as $path) {
            foreach (TestRecordCollection::fromPath($path) as $test) {
                yield $test->name => [$test];
            }
        }
    }
}
