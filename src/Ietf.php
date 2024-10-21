<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeZone;
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

    public function publishedAt(): DateTimeImmutable
    {
        return match ($this) {
            self::Rfc9651 => new DateTimeImmutable('2024-09-01', new DateTimeZone('UTC')),
            self::Rfc8941 => new DateTimeImmutable('2021-02-01', new DateTimeZone('UTC')),
        };
    }

    public function isObsolete(): bool
    {
        return match ($this) {
            self::Rfc9651 => false,
            self::Rfc8941 => true,
        };
    }

    public function supports(mixed $type): bool
    {
        if ($type instanceof StructuredField) {
            try {
                $type->toHttpValue($this);

                return true;
            } catch (Throwable) {
                return false;
            }
        }

        if (!$type instanceof Type) {
            $type = Type::tryFromVariable($type);
        }

        return match ($type) {
            null => false,
            Type::DisplayString,
            Type::Date => self::Rfc8941 !== $this,
            default => true,
        };
    }
}
