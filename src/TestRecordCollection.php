<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;
use IteratorAggregate;
use JsonException;
use RuntimeException;
use function basename;
use function fclose;
use function fopen;
use function is_resource;
use function json_decode;
use function stream_get_contents;

/**
 * @implements IteratorAggregate<string, TestRecord>
 */
final class TestRecordCollection implements IteratorAggregate
{
    private function __construct(
        /** @var array<string, TestRecord> */
        private array $elements = []
    ) {
    }

    public function add(TestRecord $test): void
    {
        if (isset($this->elements[$test->name])) {
            throw new RuntimeException('Already existing test name `'.$test->name.'`');
        }

        $this->elements[$test->name] = $test;
    }

    /**
     * @param resource|null $context
     *
     * @throws JsonException
     */
    public static function fromPath(string $path, $context = null): self
    {
        $args = [$path, 'r'];
        if (null !== $context) {
            $args[] = false;
            $args[] = $context;
        }

        $resource = @fopen(...$args);
        if (!is_resource($resource)) {
            throw new RuntimeException("unable to connect to the path `$path`.");
        }

        /** @var string $content */
        $content = stream_get_contents($resource);
        fclose($resource);

        /** @var array<array{
         *     name: string,
         *     header_type: string,
         *     raw: array<string>,
         *     canonical?: array<string>,
         *     must_fail?: bool,
         *     can_fail?: bool
         * }> $records */
        $records = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $suite = new self();
        foreach ($records as $offset => $record) {
            $record['name'] = basename($path).' #'.($offset + 1).': '.$record['name'];
            $suite->add(TestRecord::fromDecoded($record));
        }

        return $suite;
    }

    /**
     * @return Iterator<string, TestRecord>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $element) {
            yield $element->name => $element;
        }
    }
}
