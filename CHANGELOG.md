# Changelog

All Notable changes to `bakame/http-strucured-fields` will be documented in this file

## [Next] - TBD

### Added

- None

### Fixed

- `InnerList::fromHttpValue` accepts Optional White Spaces at the start of its textual representation.
- `Parameters::fromHttpValue` accepts Optional White Spaces at the start of its textual representation.

### Deprecated

- None

### Removed

- None

## [0.4.0] - 2022-03-27

### Added

- `Dictionary::mergeAssociative` and `Dictionary::mergePairs` to allow merging with associative structures
- `Parameters::mergeAssociative` and `Parameters::mergePairs` to allow merging with key-value pairs structures

### Fixed

- All containers `Dictionary`, `InnerList`, `OrderedList`, `Parameters` modifying methods are made chainable.
- `Parser` only returns `array`'s of items or bare items value.
- All `Parameters` getters checks for bare items validity.
- `ForbiddenStateError` extends SPL `LogicException` instead of `UnexpectedValueException`

### Deprecated

- None

### Removed

- **[BC Break]** `Dictionary::merge` replaced by `Dictionary::mergeAssociative`
- **[BC Break]** `Parameters::merge` replaced by `Parameters::mergeAssociative`

## [0.3.0] - 2022-03-21

### Added

- `InnerList::fromHttpValue` named constructor to make the public API consistent for all VOs

### Fixed

- None

### Deprecated

- None

### Removed

- None

## [0.2.0] - 2022-03-20

### Added

- `Item::value` is a public readonly property that gives access to the item value
- `Item::parameters` is a public readonly property that gives access to the item parameters
- `InnerList::parameters` is a public readonly property that gives access to the list parameters
- `OrderedList::from` named constructor which accepts a variadic list of members items
- `Token::fromString` named constructor which accepts `string` and `Stringable` object
- `Parameter::values` returns an array of all the values contained inside the `Parameters` instance
- **[BC Break]** `ForbiddenStateError` to replace `SerializationError` 
- **[BC Break]** `InnerList::fromList` to replace `InnerList::fromMembers`
- **[BC Break]** `OrderedList::fromList` to replace `OrderedList::fromMembers`
- **[BC Break]** `Parameter::value` to replace `InnerList::parameter` and `Item::parameter`

### Fixed

- `ByteSequence::fromDecoded` named constructor also accepts a `Stringable` object
- `ByteSequence::fromEncoded` named constructor also accepts a `Stringable` object
- `Dictionary::merge` accepts any iterable that can be accepted by `Dictionary::fromAssociative` as variadic parameter
- `Parameter::merge` accepts any iterable that can be accepted by `Parameter::fromAssociative` as variadic parameter
- **[BC Break]** `OrderedList::__construct` is made private use `OrderedList::from` instead
- **[BC Break]** `InnerList::__construct` is made private use `InnerList::fromList` instead
- **[BC Break]** `Token::__construct` is made private use `Token::fromString` instead
- **[BC Break]** `Parameter::get`, `Parameter::value`, `Parameter::pair` will throw `ForbiddenStateError` if the BareItem is in invalid state.

### Deprecated

- None

### Removed

- **[BC Break]** `InnerList::fromMembers` replaced by `InnerList::fromList`
- **[BC Break]** `OrderedList::fromMembers` replaced by `OrderedList::fromList`
- **[BC Break]** `Item::parameter` replaced by `Parameter::value`
- **[BC Break]** `InnerList::parameter` replaced by `Parameter::value`
- **[BC Break]** `SupportsParameters` interface is removed without replacement
- **[BC Break]** `Item::value()` replaced by `Item::value` public readonly property
- **[BC Break]** `Item::parameters()` replaced by `Item::parameters` public readonly property
- **[BC Break]** `InnerList::parameters()` replaced by `InnerList::parameters` public readonly property
- **[BC Break]** `InnerList::merge()` use `InnerList::push()` or `InnerList::unshift()` instead
- **[BC Break]** `OrderedList::merge()` use `OrderedList::push()` or `OrderedList::unshift()` instead
- **[BC Break]** `SerializationError` use `ForbiddenStateError` instead

## [0.1.0] - 2022-03-18

**Initial release!**

[Next]: https://github.com/bakame-php/http-structured-fields/compare/0.4.0...master
[0.4.0]: https://github.com/bakame-php/http-structured-fields/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/bakame-php/http-structured-fields/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/bakame-php/http-structured-fields/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/bakame-php/http-structured-fields/releases/tag/0.1.0
