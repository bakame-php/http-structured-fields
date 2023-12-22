<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @phpstan-type RecordData array{
 *     name: string,
 *     header_type: 'dictionary'|'list'|'item',
 *     raw: array<string>,
 *     canonical?: array<string>,
 *     must_fail?: bool,
 *     can_fail?: bool
 * }
 */
final class Record
{
    private function __construct(
        public readonly string $name,
        /** @var 'dictionary'|'list'|'item' */
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
     * @param RecordData $data
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
