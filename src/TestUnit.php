<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use JsonException;

final class TestUnit
{
    public function __construct(
        public readonly string $name,
        public readonly string $headerType,
        /** @var array<string> */
        public readonly array $raw,
        /** @var array<string> */
        public readonly array $canonical,
        public readonly bool $mustFail,
        public readonly bool $canFail,
    ) {
    }

    /**
     * @throws JsonException
     */
    public static function fromJsonString(string $jsonTest): self
    {
        /** @var array{
         *     name:string,
         *     header_type: string,
         *     raw: array<string>,
         *     canonical?: array<string>,
         *     must_fail?: bool,
         *     can_fail?: bool
         * } $data */
        $data = json_decode($jsonTest, true, 512, JSON_THROW_ON_ERROR);

        return self::fromDecoded($data);
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
        $data += ['must_fail' => false, 'can_fail' => false, 'canonical' => $data['raw']];

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
