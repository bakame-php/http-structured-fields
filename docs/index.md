HTTP Structured Fields For PHP
=====================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

The package provides an expressive, minimal API around the [HTTP Structured Fields RFC][1] in PHP.
It allows the user to quickly parse, serialize, build and update HTTP fields in a predicable way.


System Requirements
-------

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

Installation
------------

Use composer:

```
composer require bakame/http-structured-fields
```

or download the library and:

- use any other [PSR-4](https://www.php-fig.org/psr/psr-4/) compatible autoloader.
- use the bundle autoloader script as shown below:

Documentation
------------

- [Basic Usage](basic-usage.md)
- [Items](item.md)
- [Containers](containers.md)
- [Ordered Maps](ordered-maps.md)
- [Lists](lists.md)


[1]: https://www.rfc-editor.org/rfc/rfc8941.html
[2]: https://www.ietf.org/id/draft-ietf-httpbis-retrofit-00.html
[3]: https://www.rfc-editor.org/rfc/rfc8941.html#section-3.3
