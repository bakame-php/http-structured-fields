# Changelog

All Notable changes to `bakame/http-strucured-fields` will be documented in this file

## [Next] - TBH

### Added

- `Token::fromString` named constructor which accepts `string` and `Stringable` object
- `OrderedList::from` named constructor which accepts a variadic list of members items

### Fixed

- `ByteSequence::fromDecoded` named constructor also accepts a `Stringable` object
- `ByteSequence::fromEncoded` named constructor also accepts a `Stringable` object
- [BC Break] `OrderedList::__construct` is made privateuse `OrderedList::from` instead
- [BC Break] `InnerList::__construct` is made private use `InnerList::fromMembers` instead
- [BC Break] `Token::__construct` is made private use `Token::fromString` instead

### Deprecated

- None

### Removed

- None

## [0.1.0] - 2022-03-18

**Initial release!**

[Next]: https://github.com/bakame-php/http-structured-fields/compare/0.1.0...master
[0.1.0]: https://github.com/bakame-php/http-structured-fields/releases/tag/0.1.0
