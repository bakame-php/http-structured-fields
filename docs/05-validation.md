---
title: Structured Field validation
order: 6
---

# Validation

When it comes to make sure about the incoming data the package provides a simple approach around validation,
To learn and understand the package validation mechanism we will look at a real world example and expand on it.

So what we are going to do is validating the following putative HTTP field `temperature`.

> The field is defined as a List. meaning it can contain multiple temperature
> definitions as items. Each temperature entry MUST contain a temperature value express in Celsius.
> The temperature has the following required parameters `date`, `longitude` and `latitude`
> and an optional `location` parameter which is a human-readable name of the location where
> the temperature was read. The `location` can be a `string` or a `displaystring`. The latitude
> and longitude are express as `decimal` values. You will find below an example of such HTTP field.

```bash
temperature: 18.3;location=%"lagos";date=@1731573026;longitude=6.418;latitude=3.389, 12.8;date=@1730894400;longitude=6.418;latitude=3.389
```

### Parsing the field

Since we learn that it is a list we can go ahead and parse it as usual:

```php
use Bakame\Http\StructuredFields\OuterList;

$fieldLine = '18.3;location=%"lagos";date=@1731573026;longitude=6.418;latitude=3.389, 12.8;date=@1730894400;longitude=6.418;latitude=3.389';
$field = OuterList::fromHttpValue($fieldLine);
count($field); // returns 2 entries.
$field->first()->value(); // returns 18.3 
$field->last()->value();  // returns 12.8
```

So far so good, the field is successfully parsed by the package.

> [!NOTE]
> The field would fail parsing with the obsolete RFC8941 definition

### Validating each entry separately

Each entry can be validated separately using a callback on the `getBy*` methods
attached to any container. Here we are using a `List` container, so we can do
the following:

```php
$temperature1 = $field->getByIndex(
    index: 1,
    validate: fn (Item|InnerList $member) => $member instanceof Item
);
```

If the `validate` callback returns `true` then it means that the value accessed by the field validate
the expected constraint otherwise it failed the constraint and a `Violation` exception is thrown.
If you omit the `validate` argument or do not pass it (which is the default) the value will get
returned as is without any check.

In my example the constraint state that the return value MUST be a `Item` so if I indeed have
a `List` but the second member of that list is an `InnerList` instead an `Violation`
exception will be thrown.

The `Violation` exception thrown will have a generic message stating that the field failed
validation. But you can adapt the error message if you want. To do so, instead of
returning `false` on error you can return a template string.

```php
$temperature1 = $field->getByIndex(
    index: 1,
    validate: function (Item|InnerList $member): bool|string {
        if ($member instanceof Item) {
            return 'The field `{index}`; `{value}` failed.';
        }
        
        return true;
    });
// will generate the following message
// The field `1`; `12.8;date=@1730894400;longitude=6.418;latitude=3.389` failed.
```

The template string can return the incoming data if needed for logging. It supports the
following variables:

- `{index}` the member index
- `{value}` the member value in its serialized version
- `{name}` the member name (only available with `Dictionary` and `Parameters`)

Now that we know how to discriminate between an `InnerList` and a `Item` we want to validate
the `Item` entry.

### Validating the Item value

To validate the expected value of an `Item` you need to provide a callback to the `Item::value` method.
The callback behave exactly how the callback from the container was described. The only difference
is that the expected value of the callback is one of the eight value types.

Our field definition states:

> Each temperature entry MUST contain a temperature value express in Celsius.

So it means that the `Item` value must be a decimal. Let's use the `Type` enum
to quickly validate that information

```php
use Bakame\Http\StructuredFields\Type;

$value = $member->value(Type::Decimal->supports(...));
```

The `Type` enum contains a `supports` method which returns `true` if the submitted value
is of the specified value type; otherwise it will return `false`. Again if we need 
a more specific error message in our `Violation` exception we can change the code 
to something more meaningful.

```php
use Bakame\Http\StructuredFields\Type;

$value = $member
    ->value(
        function (mixed $value): bool|string {
            if (!Type::Decimal->supports($value)) {
                return "The value '{value}' failed the RFC validation.";
            }

            return true; 
        }
    );
// the following exception will be thrown
// new Violation("The value 'foo' failed the RFC validation.");
```

> [!NOTE]
> we used `mixed` as parameter type for convenience but the effective parameter type should be
> `Byte|Token|DisplayString|DateTimeImmutable|string|int|float|bool`

### Validating the Item parameters.

### Checking for allowed names

Before validating the content of the `Parameters` container we need to make
sure the container contains the proper data. That all the allowed names are
present. To do so we can use the `Parameters::allowedNames` method. This
method expects a list of names. If other names not present in the
list are found in the container the method will return `false`. If we
go back to our definition. We know that the allowed parameters names attached
to the item are: `location`, `longitude`, `latitude` and `date`

```php
use Bakame\Http\StructuredFields\Validation\Violation;

if (!$member->parameters()->allowedNames(['location', 'longitude', 'latitude', 'date'])) {
    throw new Violation('The parameters contains extra names that are not allowed.');
}
```

> [!TIP]
> The `Dictionary` class also exposes an `allowedNames` method which behave the same way.

> [!WARNING]
> if the parameters container is empty no error will be triggered

### Validating single parameters

The `parameterByName` and `parameterByIndex` methods can be used to validate a parameter value.
Since in our field there is no mention of offset, we will use the `::parameterByName` method.

Let's try to validate the `longitude` parameter

Because parameters are optional by default and the `longitude` parameter is required we must
require its presence. So to fully validate the parameter we need to do the following

```php
$member->parameterByName(
    name: 'longitude',
    validate: fn (mixed $value) => match (true) {
        Type::Decimal->supports($value) => true,
        default => "The `{name}` '{value}' failed the validation check."
    },
    required: true,
);
```

> [!NOTE]
> `parameterByIndex` uses the same parameter only the callback parameter are
> different as a second parameter the string name is added to the callback
> for validation purpose.

### Validating the complete Parameter container

We could iterate the same type of code for each parameter separately but the code
would quickly become complex. So to avoid repetition, the package
introduces a `ParametersValidator`.

To instantiate this class you just need to call its `new` static method.

```php
use Bakame\Http\StructuredFields\Validation\ParametersValidator;

$parametersValidator = ParametersValidator::new()
```

This class can aggregate all the rules for a parameter container, applies them all at
once and returns a result you can use to quickly know whether your parameters do meet
all the criteria.

Going back to the HTTP field definitions we can translate the requirements and create the
following `ParametersValidator`.

We need to make sure about the allowed names for that. the class has a `filterByCriteria` which
expects the `Parameters` container as its sole argument.

```php
$parametersValidator = ParametersValidator::new()
    ->filterByCriteria(function (Parameters $parameters): bool|string {
        return $parameters->allowedNames(['location', 'longitude', 'latitude', 'date']);
    });
```

The `ParametersValidator` class is immutable so each added rules returns a new instance.

Then we can add all the name checks using an associative `array` where each entry index
will be the parameter `name` and each entry value will also be an array which takes
the parameters of the `parameterByName` method. For instance for the `longitude` parameter
we did earlier we end up with the following entries.

```php
use Bakame\Http\StructuredFields\Type;

$parametersValidator = ->filterByNames([
        'longitude' => [
            'validate' => function (mixed $value) {
                 if (!Type::Decimal->supports($value)) {
                    return "The `{name}` '{value}' failed the validation check.";
                 }

                 return true; 
            },
            'required' => true,
        ],
    ]);
```

We can do the same for all the other names, the available parameters are:
- `validate`: the callback used for validation; `null` by default
- `required`: a boolean telling whether the parameter presence is required; `false` by default
- `default`: the default value if the parameter is optional; `null` by default.

if we put together the class to validate our parameters we end up with the following code.

```php
use Bakame\Http\StructuredFields\Parameters;
use Bakame\Http\StructuredFields\Type;
use Bakame\Http\StructuredFields\Validation\ParametersValidator;

$parametersValidator = ParametersValidator::new()
    ->filterByCriteria(
        fn (Parameters $parameters): bool|string => $parameters
            ->allowedNames(['location', 'longitude', 'latitude', 'date'])
    )
    ->filterByNames([
        'location' => [
            'validate' => fn (mixed $value) => Type::fromVariable($value)->isOneOf(Type::String, Type::DisplayString),
        ],
        'longitude' => [
            'validate' => function (mixed $value) {
                 if (!Type::Decimal->supports($value)) {
                    return "The `{name}` '{value}' failed the validation check.";
                 }

                 return true; 
            },
            'required' => true,
        ],
        'latitude' => [
            'validate' => function (mixed $value) {
                 if (!Type::Decimal->supports($value)) {
                    return "The `{name}` '{value}' failed the validation check.";
                 }

                 return true; 
            },
            'required' => true,
        ],
        'date' => [
            'validate' => function (mixed $value) {
                 if (!Type::Date->supports($value)) {
                    return "The `{name}` '{value}' is not a valid date";
                 }

                 return true; 
            },
            'required' => true,
        ]
    ]);
```

We can now validate the parameters by calling the `ParametersValidator::validate` method:

```php
$validation = $parametersValidator->validate($members->parameters());
if ($validation->isFailed()) {
    throw $validation->errors->toException(); 
    // throws a Violation exception whose error messages contains all the error messages found.
}
```

The `$result` is a `Result` class which tells whether the validation was successfully
performed or not. In case of errors, the class exposes a `ViolationList` collection via its 
public readonly property `errors` which contains all the `Violation` exceptions triggered
during the validation process. In case of success, the class will return the filtered
data via it's public readonly property `data`. 

```php
$validation = $parametersValidator->validate($members->parameters());
if ($validation->isSucces()) {
    $parameters = $validation->data->all();
    $parameters['longitude']; // 6.418
    $parameters['location'];  // null
    $parameters['date'];      // new DateTimeImmutable('@1730894400');
}
```

> [!NOTE]
> If we only had validated the `longitude` parameter. it would have been
> the only one present in the returned data.

> [!NOTE]
> If we only use the `filterByCriteria` method the full parameter data is returned.

 A `filterByIndices` method exists and behave exactly as the `filterByNames` method.
There are two differences when it is used:
 
- The callback parameters are different (they match those of `parameterByIndex`)
- The returned parameters data in case of success is different

```php
$validation = $parametersValidator->validate($members->parameters());
if ($validation->isSucces()) {
    $parameters = $validation->data->all();
    $parameters[0]; // returns ['longitude', 6.418]
    $parameters[1]; // returns ['location', null];
    $parameters[2]; // returns ['date', new DateTimeImmutable('@1730894400')];
}
```

> [!IMPORTANT]
> Both methods are mutually exclusive if you use them both, the last one used will 
> be the one which format the returned data. 

### Validating the full Item

Now that we have validated the parameters and the item value. It would be nice to 
validate the `Item` once with all the rules. To do so, let's use the `ItemValidator`
class.

```php
use Bakame\Http\StructuredFields\Validation\ItemValidator;
use Bakame\Http\StructuredFields\Validation\ParametersValidator;
use Bakame\Http\StructuredFields\Type;

$itemValidator = ItemValidator::new()
    ->value(
        function (mixed $value) {
            if (!Type::Decimal->supports($value)) {
                return "The value '{value}' failed the RFC validation.";
            }
            
            return true; 
        }
    )
    ->parameters($parametersValidator);
$result = $itemValidator->value($member);
```

And just like with the `ParametersValidator` we get a `Validation\Result` DTO.

In case of failure we have the same behaviour.

```php
$validation = $itemValidator->validate($members->parameters());
if ($validation->isFailed()) {
    throw $validation->errors->toException(); 
    // throws a Violation exception whose error messages contains all the error messages found.
}
```

In case of success, the return data is slightly different:

```php
$validation = $itemValidator->validate($members->parameters());
if ($validation->isSuccess()) {
    $itemValue = $validation->data->value; // returns 12.8
    $parameters = $validation->data->parameters->all();
    $parameters['longitude']; // 6.418
    $parameters['location'];  // null
    $parameters['date'];      // new DateTimeImmutable('@1730894400');
}
```

So now let's go back to where we started.

```php
$temperatureValidator = function (Item|InnerList $member) use ($itemValidator): bool|string {
    if (!$member instanceof Item) {
        return 'The field `{index}`; `{value}` failed.';
    }

    return $itemValidator($member);
};

$temperature1 = $field->getByIndex(index: 1, validate: $temparatureValidator);
```

The `ParametersValidator` and the `ItemValidator` are invokable, so we can use them
directly with the `getBy*` methods. So once you have configured your validator in a
class it becomes easier to reuse it to validate your data.

> [!NOTE]
> When used as invokable the validators return `true` on success and the aggregated 
> error messages as string on error.

> [!TIP]
> A best practice is to move all this validation definition in its own class, and use that 
> class instead to ease maintenance and testing.

> [!TIP]
> Once you have a specific class to validate a single entry for your list or dictionary it is 
> easy to validate all the container using either the `map`, `filter` or the `reduce` method 
> associated with each container.

To show how this can be achieved you can check the codebase from [HTTP Cache Status](https://github.com/bakame-php/http-cache-status)

&larr; [Containers](04-containers.md)  | [Extending the package functionalities](07-extensions.md)
