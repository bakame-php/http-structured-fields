<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

final class TestUnit
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        /** @var array<string> */
        public readonly array $raw,
        /** @var array<string> */
        public readonly array $canonical,
        public readonly bool $mustFail,
        public readonly bool $canFail,
    ) {
    }

    /**
     * @param array{
     *     name:string,
     *     header_type:string,
     *     raw:array<string>,
     *     canonical?: array<string>,
     *     must_fail?: bool,
     *     can_fail?: bool
     * } $data
     */
    public static function fromDecoded(array $data): self
    {
        $data += ['canonical' => $data['raw'], 'must_fail' => false, 'can_fail' => false];

        return new self(
            $data['name'],
            $data['header_type'],
            $data['raw'],
            $data['canonical'],
            $data['must_fail'],
            $data['can_fail'],
        );
    }
}
