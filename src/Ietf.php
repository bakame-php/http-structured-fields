<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use DateTimeImmutable;
use DateTimeZone;

enum Ietf
{
    case Rfc8941;
    case Rfc9651;

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
            default => true,
        };
    }

    public function supports(mixed $value): bool
    {
        if ($value instanceof StructuredFieldProvider) {
            $value = $value->toStructuredField();
        }

        if ($value instanceof OuterList ||
            $value instanceof InnerList ||
            $value instanceof Dictionary ||
            $value instanceof Parameters ||
            $value instanceof Item
        ) {
            try {
                $value->toHttpValue($this);

                return true;
            } catch (MissingFeature) {
                return false;
            }
        }

        if (!$value instanceof Type) {
            $value = Type::tryFromVariable($value);
        }

        return match ($value) {
            null => false,
            Type::DisplayString,
            Type::Date => self::Rfc8941 !== $this,
            default => true,
        };
    }
}
