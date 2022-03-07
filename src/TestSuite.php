<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredField;

use Closure;
use Iterator;
use IteratorAggregate;
use JsonException;
use RuntimeException;

/**
 * @implements IteratorAggregate<string, TestUnit>
 */
final class TestSuite implements IteratorAggregate
{
    /** @var array<string, TestUnit> */
    private array $elements;

    public function __construct(TestUnit ...$elements)
    {
        $this->elements = [];
        foreach ($elements as $element) {
            $this->add($element);
        }
    }

    public function add(TestUnit $test): void
    {
        if (isset($this->elements[$test->name])) {
            //throw new RuntimeException('Already existing test name `'.$test->name.'`');
            return;
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
         * }> $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $suite = new self();
        foreach ($data as $testUnit) {
            $suite->add(TestUnit::fromDecoded($testUnit));
        }

        return $suite;
    }

    /**
     * @return Iterator<string, TestUnit>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $element) {
            yield $element->name => $element;
        }
    }

    public function filter(Closure $predicate): self
    {
        $elements = array_filter($this->elements, $predicate, ARRAY_FILTER_USE_BOTH);
        if ($this->elements === $elements) {
            return $this;
        }

        return new self(...$elements);
    }
}
