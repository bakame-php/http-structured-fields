---
title: Interacting with PHP ecosystem
order: 8
---

# Interacting with PHP ecosystem

All Datatypes expose the `Stringable` interface while it is recommended to use
the `toHttpValue` method for better granularity. Supporting the `Stringable`
interface allows the package to easily interface with packages and frameworks
which expects a string or a stringable object when adding or updating
HTTP field values.

```php
$container = InnerList::new(ByteSequence::fromDecoded('Hello World'), 42.0, 42);

$container->toHttpValue(); // returns '(:SGVsbG8gV29ybGQ=: 42.0 42)'
echo $container;           // returns '(:SGVsbG8gV29ybGQ=: 42.0 42)' 
```

## Everything is final

As a rule of thumb all the classes from this package are final and immutable and
expose no particular interface.

The reason is to ensure that in any situation the RFC is being respected. The contract
is between the RFC and your code the package only acts as a link between both parties.

## Closed for extension opened for composition

On the other hand to allow composition the package does expose the `StructuredFieldProvider` interface.

```php
interface StructuredFieldProvider
{
    /**
     * Returns one of the StructuredField Data Type class.
     */
    public function toStructuredField(): Dictionary|InnerList|Item|OuterList|Parameters;
}
```

This interface should return one of the DataType class and this is the interface that needs
to be implemented. All the containers are able to work with a `StructuredFieldProvider`.

Imagine you have an `AcceptHeaderItem` class, and you want to take advantage of the package. You
only have to implement the `StructuredFieldProvider`. Once done, your class will be able to
work with the `OuterList` class.

```php
class readonly AcceptHeaderItem
{
    public function __construct(
        public string $value;
        public float $quality = 1.0;
    ) {}
}
```

To use the package you will have to add the missing interface

```php
class readonly AcceptHeaderItem implements StructuredFieldProvider
{
    public function __construct(
        public string $value,
        public float $quality = 1.0
    ) {}

    public function toStructuredField(): Item
    {
        return Item::fromToken($this->value)
            ->withParameters(
                Parameters::new()
                    ->append('q', $this->quality)
                    ->filter(fn (array $pair): bool => $pair[0] !== 'q' || 1.0 !== $pair[1]->value())
                );
    }
}
```

Now this class can be used with the `OuterList` class to properly serialize an `Accept` header
as mandated by the RFC.

```php
$json = new AcceptHeaderItem('application/json');
$csv = new AcceptHeaderItem('text/csv', 0.7);

echo OuterList::new($json, $csv);
//returns application/json, text/csv;q=0.7
```

In the example provided we added the interface on the class itself but of course you are free to use
a different approach, as long as you end up have a class that implements the `StructuredFieldProvider`
contract.

To show how this can be achieved you can check the codebase from [HTTP Cache Status](https://github.com/bakame-php/http-cache-status)
which uses the interface. Of note by using this interface you can completely hide the presence of 
this package to your end users if needed.

&larr; [Validation](06-validation.md) 
