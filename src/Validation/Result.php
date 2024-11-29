<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields\Validation;

final readonly class Result
{
    private function __construct(
        public ValidatedParameters|ValidatedItem|null $data,
        public ViolationList $errors,
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
