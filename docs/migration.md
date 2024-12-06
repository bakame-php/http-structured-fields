---
title: Migrating from version 1.x
order: 8
---

# Upgrading from version 1.x to 2.0

`bakame/http-structured-fields 2.0` is a new major version that comes with backward compatibility breaks.
This guide will help you migrate from a 2.x version to 2.0. It will only explain backward compatibility breaks, 
it will not present the new features,

## Installation

If you are using composer then you should update the `require` section of your `composer.json` file.

```php
composer require bakame/http-structured-fields:^2.0
```

This will edit (or create) your `composer.json` file.

## PHP version requirement

`bakame/http-structured-fields 2.0` requires a PHP version greater or equal to `8.1` as does the previous version.

## Interfaces

### Structured Field Interfaces

All the Interfaces around the structured field data types are removed. So if you type-hinted your code using
the interfaces, you will need to replace them by their actual implementation.

- The `MemberOrderedMap` interface will need to be replaced either by `Dictionary` or `Parameters` classes
- The `MemberList` interface will need to be replaced either by `OuterList` or `InnerList` classes
- The `ParameterAccess` interface will need to be replaced either by `Item` or `InnerList` classes
- The `ValueAccess` interface will need to be replaced either by the `Item` class

### Parser Interfaces

The Parser related interfaces are completely removed, the parser is now an internal implementation detail which has no
facing public API.

```diff
- public static function fromHttpValue(Stringable|string $httpValue, DictionaryParser $parser = new Parser()): self
+ public static function fromHttpValue(Stringable|string $httpValue, ?Ietf $rfc = null): self
```

In v2, the parser instance is replaced by an Enum that indicates which RFC should be used for parsing.
If your code did not provide any second parameter to the method then the parsing will be done using `RFC9651`
if you want to only consider the previous active RFC, then you will have to explicitly name it via the `Ietf` Enum.

## Method renaming

`Dictionary` and `Parameters` container members can be accessed vi their name or via their index.
To normalize the accessor methods the following changes were introduced

| `1.x` method name                   | `2.x` method name            |
|-------------------------------------|------------------------------|
| `Item::parameter`                   | `Item::parameterByKey`       |
| `InnerList::parameter`              | `InnerList::parameterByKey`  |
| `InnerList::has`                    | `InnerList::hasIndices`      |
| `InnerList::get`                    | `InnerList::getIndex`        |
| `OuterList::get`                    | `OuterList::getIndex`        |
| `OuterList::has`                    | `OuterList::hasIndices`      |
| `Dictionary::has`                   | `Dictionary::hasKeys`        |
| `Dictionary::get`                   | `Dictionary::getByKey`       |
| `Dictionary::pair`                  | `Dictionary::getByIndex`     |
| `Parameters::has`                   | `Parameters::hasKeys`        |
| `Parameters::get`                   | `Parameters::getByKey`       |
| `Parameters::pair`                  | `Parameters::getByIndex`     |
| `Container::remove`                 | `Container::removeByIndices` |
| `Container::hasNoMember`            | `Container::isEmpty`         |
| `Container::hasMembers`             | `Container::isNotEmpty`      |

The `Parameters::remove` and `Dictionary::remove` methods are removed from the public API, they
were accepting `indices` and `keys` indiscriminately which may lead to subtle bugs in code.

## Behaviour changes

To normalize container usage, performing `foreach` on a Container will always make the iteration return the an
`Iterator` whose offset is the member indices and the value is:

- the member value itself if the container is a list (`OuterList`, `InnerList`)
- an array with 2 entries, the first entry is the member name and the second entry, the member value. (`Dictionary`, `Parameters`)

```pho
$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
//the raw header line is a structured field dictionary
$permissions = DataType::Dictionary->parse($headerLine); // parse the field
```

In version 1:

```php
foreach ($permissions as $offset => $member) {
    //first iteration
    //$offset 'picture-in-picture
    //$member InnerList::new()
}
```

In version 2:

```php
foreach ($permissions as $offset => $member) {
    //first iteration
    //$offset 0
    //$member ['picture-in-picture', InnerList::new()]
}
````

You can access the v1 behaviour using the `toAssociative` method. The method
exists on the `Dictionary` and `Parameters` containers.

In version 2:

```php
foreach ($permissions->toAssociative() as $offset => $member) {
    //first iteration
    //$offset 'picture-in-picture
    //$member InnerList::new()
}
````

## Auto-conversion

If the `Item` or the `InnerList` have no parameters attached to them you can now ignore the parameters value all together,
this is not possible in v1.

In version 1:

```php
OuterList::fromPairs([
    [
        [['foo', []], ['bar', []]],
    ]
])->toHttpValue());
```

In version 2:

```php
OuterList::fromPairs([
    [
        ['foo', 'bar'],
    ]
])->toHttpValue());
```

> [!NOTE]
> The v1 syntax is still supported.

&larr; [Extending the package functionalities](extensions.md)  |  [Intro](index.md) &rarr;
