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

        $input = implode(',', $test->raw);
        $item = match ($test->headerType) {
            'dictionary' => Dictionary::fromField($input),
            'list' => OrderedList::fromField($input),
            default => Item::fromField($input),
        };

        if (!$test->mustFail) {
            $expected = implode(',', $test->canonical);
            self::assertSame($expected, $item->toField());
        }
    }

    /**
     * @throws JsonException
     * @return iterable<string, array<TestUnit>>
     */
    public function httpWgDataProvider(): iterable
    {
        foreach ($this->paths as $path) {
            $prefix = basename($path, '.json');
            foreach (TestSuite::fromPath($path) as $test) {
                yield $prefix.': '.$test->name => [$test];
            }
        }
    }
}
