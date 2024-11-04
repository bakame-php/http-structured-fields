<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

/**
 * @template T
 */
final class Result
{
    /**
     * @param ?T $data
     */
    private function __construct(
        public readonly mixed $data,
        public readonly ViolationList $errors,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->errors->hasNoError();
    }

    public function isFailed(): bool
    {
        return $this->errors->hasErrors();
    }

    /**
     * @param T $data
     *
     * @return self<T>
     */
    public static function success(mixed $data): self
    {
        return new self($data, new ViolationList());
    }

    /**
     * @return self<null>
     */
    public static function failed(ViolationList $errors): self
    {
        return new self(null, $errors);
    }
}
