<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PhpBench\Attributes as Bench;

final class ParserBench
{
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingAListFormAnHTTPHeaderValue(): void
    {
        $httpValue = '("lang" "en-US"); expires=@1623233894; samesite=Strict; secure';
        $parser = Parser::new();
        for ($i = 0; $i < 100_000; $i++) {
            $parser->parseList($httpValue);
        }
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingAnItemFormAnHTTPHeaderValue(): void
    {
        $httpValue = '"lang"; expires=@1623233894; samesite=Strict; secure';
        $parser = Parser::new();
        for ($i = 0; $i < 100_000; $i++) {
            $parser->parseItem($httpValue);
        }
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingAnDictionaryFormAnHTTPHeaderValue(): void
    {
        $httpValue = 'lang="en-US"; samesite=Strict; secure, type=42.0; expires=@1623233894';
        $parser = Parser::new();
        for ($i = 0; $i < 100_000; $i++) {
            $parser->parseDictionary($httpValue);
        }
    }
}
