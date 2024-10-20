<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Throwable;

enum Ietf: string
{
    case Rfc8941 = 'RFC8941';
    case Rfc9651 = 'RFC9651';

    public function uri(): string
    {
        return match ($this) {
            self::Rfc9651 => 'https://www.rfc-editor.org/rfc/rfc9651.html',
            self::Rfc8941 => 'https://www.rfc-editor.org/rfc/rfc8941.html',
        };
    }

    public function isObsolete(): bool
    {
        return match ($this) {
            self::Rfc9651 => false,
            self::Rfc8941 => true,
        };
    }

    public function supports(Type|StructuredField $type): bool
    {
        if ($type instanceof Type) {
            return match ($type) {
                Type::DisplayString,
                Type::Date => self::Rfc8941 !== $this,
                default => true,
            };
        }

        try {
            $type->toHttpValue($this);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function publishedAt(): string
    {
        return match ($this) {
            self::Rfc9651 => '2024-09',
            self::Rfc8941 => '2021-02',
        };
    }
}
