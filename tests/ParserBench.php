<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PhpBench\Attributes as Bench;

final class ParserBench
{
    private readonly Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser(Ietf::Rfc9651);
    }

    #[Bench\Iterations(4)]
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingAList(): void
    {
        $httpValue = '("lang" "en-US" token); expires=@1623233894; samesite=Strict; secure';
        for ($i = 0; $i < 100_000; $i++) {
            $this->parser->parseList($httpValue);
        }
    }

    #[Bench\Iterations(4)]
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingAnItem(): void
    {
        $httpValue = '"lang"; expires=@1623233894; samesite=Strict; secure';
        for ($i = 0; $i < 100_000; $i++) {
            $this->parser->parseItem($httpValue);
        }
    }

    #[Bench\Iterations(4)]
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchParsingAnDictionary(): void
    {
        $httpValue = 'code=token, lang="en-US"; samesite=Strict; secure, type=42.0; expires=@1623233894';
        for ($i = 0; $i < 100_000; $i++) {
            $this->parser->parseDictionary($httpValue);
        }
    }
}
