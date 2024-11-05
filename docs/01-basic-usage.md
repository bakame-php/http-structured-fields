# Basic Usage

## Parsing the Field

The first way to use the package is to enable header or trailer parsing. We will refer to them as field
for the rest of the documentation as it is how they are called hence the name of the RFC HTTP structured fields.

Let's say we want to parse the [Permissions-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Permissions-Policy#syntax) field as it is defined.
Because the field is defined as a Dictionary we can easily parse it using the package as follows:

```php
$headerLine = 'picture-in-picture=(), geolocation=(self "https://example.com/"), camera=*'; 
//the raw header line is a structured field dictionary
$permissions = Dictionary::fromHttpValue($headerLine); // parse the field
```

You can now access each permission individually as follows:

```php
$permissions['picture-in-picture']->hasNoMembers(); //returns true because the list is empty
$permissions['geolocation'][1]->value(); //returns 'https://example.com/'
count($permissions['geolocation']); // returns 2
$permissions['camera']->value(); //returns '*'
```

Apart from following the specification some syntactic sugar methods have been added to allow for easy access
of the values.

> [!WARNING]
> If parsing fails a `SyntaxError` exception is thrown with the information about why the conversion
> could not be achieved.

## Building the Field

Conversely, if you need to update the permission field, the package allows for an intuitive way to do so:

```php
$newPermissions = $permissions
    ->removeByKeys('camera')
    ->add('gyroscope', [
        Token::fromString('self'), 
        "https://a.example.com",
        "https://b.example.com"
    ]);
echo $newPermissions; 
//returns picture-in-picture=(), geolocation=(self "https://example.com/"), gyroscope=(self "https://a.example.com" "https://b.example.com")
```

Just like with parsing, if the value given is not supported by the structured field `Dictionary` specification
an exception will be thrown. All RFC related classes in the package are final and immutable to allow easier
and predicable manipulation. 

## Structured Fields Values

For a more in depth presentation of each structure and their method please head on over to the next chapter 
to understand the RFC data types and how they relate to PHP.

[Value types](/docs/02-types.md) →
