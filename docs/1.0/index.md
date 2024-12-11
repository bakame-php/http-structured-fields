---
layout: default
title: Overview
---

# Overview

`bakame/http-structured-fields` is a framework-agnostic PHP library that allows you to parse, serialize
create and update HTTP Structured Fields in PHP according to the [RFC8941](https://www.rfc-editor.org/rfc/rfc8941.html).

Once installed you will be able to do the following:

```php
use Bakame\Http\StructuredFields\DataType;
use Bakame\Http\StructuredFields\Token;

//1 - parsing an Accept Header
$fieldValue = 'text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8';
$field = DataType::List->parse($fieldValue);
$field[2]->value()->toString(); // returns 'application/xml'
$field[2]->parameter('q');      // returns (float) 0.9
$field[0]->value()->toString(); // returns 'text/html'
$field[0]->parameter('q');      // returns null

//2 - building a retrofit Cookie Header
echo DataType::List->serialize([
    [
        ['foo', 'bar'],
        [
            ['expire', new DateTimeImmutable('2023-04-14 20:32:08')],
            ['path', '/'],
            [ 'max-age', 2500],
            ['secure', true],
            ['httponly', true],
            ['samesite', Token::fromString('lax')],
        ]
    ],
]);
// returns ("foo" "bar");expire=@1681504328;path="/";max-age=2500;secure;httponly=?0;samesite=lax
```

## System Requirements

**PHP >= 8.1** is required but the latest stable version of PHP is recommended.

## Installation

Use composer:

```
composer require bakame/http-structured-fields
```
