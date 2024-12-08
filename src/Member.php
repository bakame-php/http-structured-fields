<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use function count;
use function in_array;
use function is_array;
use function is_iterable;

/**
 * @phpstan-import-type SfMemberInput from StructuredFieldProvider
 * @phpstan-import-type SfItemInput from StructuredFieldProvider
 * @phpstan-import-type SfItemPair from StructuredFieldProvider
 * @phpstan-import-type SfInnerListPair from StructuredFieldProvider
 * @phpstan-import-type SfTypeInput from StructuredFieldProvider
 *
 * @internal Validate containers member
 */
final class Member
{
    /**
     * @param SfMemberInput $value
     */
    public static function innerListOrItem(mixed $value): InnerList|Item
    {
        if ($value instanceof StructuredFieldProvider) {
            $value = $value->toStructuredField();
            if ($value instanceof Item || $value instanceof InnerList) {
                return $value;
            }

            throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Item::class.' or an '.InnerList::class.'; '.$value::class.' given.');
        }

        return match (true) {
            $value instanceof InnerList,
            $value instanceof Item => $value,
            is_iterable($value) => InnerList::new(...$value),
            default => Item::new($value),
        };
    }

    public static function innerListOrItemFromPair(mixed $value): InnerList|Item
    {
        if ($value instanceof StructuredFieldProvider) {
            $value = $value->toStructuredField();
            if ($value instanceof Item || $value instanceof InnerList) {
                return $value;
            }

            throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Item::class.' or an '.InnerList::class.'; '.$value::class.' given.');
        }

        if ($value instanceof InnerList || $value instanceof Item) {
            return $value;
        }

        if (!is_array($value)) {
            if (is_iterable($value)) {
                throw new SyntaxError('The value must be an Item value not an iterable.');
            }

            return Item::new($value); /* @phpstan-ignore-line */
        }

        if (!array_is_list($value)) {
            throw new SyntaxError('The pair must be represented by an array as a list.');
        }

        if ([] === $value) {
            return InnerList::new();
        }

        if (!in_array(count($value), [1, 2], true)) {
            throw new SyntaxError('The pair first member represents its value; the second member is its associated parameters.');
        }

        return is_iterable($value[0]) ? InnerList::fromPair($value) : Item::fromPair($value);
    }

    /**
     * @param SfItemInput|SfItemPair $value
     */
    public static function item(mixed $value): Item
    {
        if ($value instanceof StructuredFieldProvider) {
            $value = $value->toStructuredField();
            if (!$value instanceof Item) {
                throw new InvalidArgument('The '.StructuredFieldProvider::class.' must provide a '.Item::class.'; '.$value::class.' given.');
            }

            return $value;
        }

        if ($value instanceof Item) {
            return $value;
        }

        return Item::new($value);
    }

    /**
     * @param SfItemInput|SfItemPair $value
     */
    public static function bareItem(mixed $value): Item
    {
        $bareItem = self::item($value);
        if ($bareItem->parameters()->isNotEmpty()) {
            throw new InvalidArgument('The "'.$bareItem::class.'" instance is not a Bare Item.');
        }

        return $bareItem;
    }
}
