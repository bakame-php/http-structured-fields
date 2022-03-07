<?php

/**
 * League.Period (https://github.com/bakame-php/http-sfv).
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bakame\Http\StructuredField;

use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @template TKey
 * @template TValue
 *
 * @extends IteratorAggregate<TKey, TValue>
 */
interface StructuredFieldContainer extends Countable, IteratorAggregate, StructuredField
{
    public function isEmpty(): bool;

    public function findByKey(string $key): Item|InnerList|null;

    public function findByIndex(int $index): Item|InnerList|null;

    /**
     * @return Iterator<TKey, TValue>
     */
    public function getIterator(): Iterator;
}
