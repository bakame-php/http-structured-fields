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
    public function it_can_pass_http_wg_tests(TestUnit $test): void
    {
        if ($test->mustFail) {
            $this->expectException(SyntaxError::class);
        }

        $item = TestHeaderType::from($test->headerType)->fromField(implode(',', $test->raw));

        if (!$test->mustFail) {
            self::assertSame(implode(',', $test->canonical), $item->toField());
        }
    }

    /**
     * @throws JsonException
     * @return iterable<string, array<TestUnit>>
     */
    public function httpWgDataProvider(): iterable
    {
        foreach ($this->paths as $path) {
            foreach (TestSuite::fromPath($path) as $test) {
                yield $test->name => [$test];
            }
        }
    }
}
