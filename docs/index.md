Structured Field Values for PHP
=======================================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/http-structured-fields.svg?style=flat-square)](https://github.com/bakame-php/http-structured-fields/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/http-structured-fields.svg?style=flat-square)](https://packagist.org/packages/bakame/http-structured-fields)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

The package uses value objects to parse, serialize and build [HTTP Structured Fields][1] in PHP.

HTTP Structured fields are intended for use by specifications of new HTTP fields that wish to 
use a common syntax that is more restrictive than traditional HTTP field values or could
be used to [retrofit current headers][2] to have them compliant with the new syntax.

The package can be used to:

- parse and serialize HTTP Structured Fields
- build or update HTTP Structured Fields in a predicable way;

```php
use Bakame\Http\StructuredFields;

$field = StructuredFields\Item::from("/terms", ['rel' => 'copyright', 'anchor' => '#foo']);
echo $field->toHttpValue();            //display "/terms";rel="copyright";anchor="#foo"
echo $field->value;                    //display "/terms"
echo $field->parameters->value('rel'); //display "copyright"
```

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

~~~php
require 'path/to/http-structured-fields/repo/autoload.php';

use Bakame\Http\StructuredFields;

$list = StructuredFields\OrderedList::fromHttpValue('"/member/*/author", "/member/*/comments"');
echo $list[-1]->value; //returns '/member/*/comments'
~~~

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
