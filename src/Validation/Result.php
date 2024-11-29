<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

final class Result
{
    private function __construct(
        public readonly ValidatedParameters|ValidatedItem|null $data,
        public readonly ViolationList $errors,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->errors->isEmpty();
    }

    public function isFailed(): bool
    {
        return $this->errors->isNotEmpty();
    }

    public static function success(ValidatedItem|ValidatedParameters $data): self
    {
        return new self($data, new ViolationList());
    }

    public static function failed(ViolationList $errors): self
    {
        return new self(null, $errors);
    }
}
