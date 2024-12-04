# HTTP Structured Fields for PHP

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize, 
create, update and validate HTTP Structured Fields in PHP according to the [RFC9651](https://www.rfc-editor.org/rfc/rfc9651.html).

Once installed you will be able to do the following:

```php
use Bakame\Http\StructuredFields\OuterList;

//1 - parsing an Accept Header
$fieldValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$container = OuterList::fromRfc9651($fieldValue);
$container[1]->value()->toString(); // returns 'application/xhtml+xml'
$container[1]->parameterByKey(key: 'q', default: 1.0); // returns 1.0 if the parameter is not defined
```

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```

## Documentation

- The documentation for **the upcoming [version 2.x](docs/) release**

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CODE OF CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Testing

The library:

- has a [PHPUnit](https://phpunit.de) test suite
- has a coding style compliance test suite using [PHP CS Fixer](https://cs.sensiolabs.org/).
- has a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).
- is compliant with [the language agnostic HTTP Structured Fields Test suite](https://github.com/httpwg/structured-field-tests).

To run the tests, run the following command from the project folder.

``` bash
composer test
```

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/http-structured-fields/contributors)

## Attribution

The package internal parser is heavily inspired by previous work done by [Gapple](https://twitter.com/gappleca) on [Structured Field Values for PHP](https://github.com/gapple/structured-fields/).

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
