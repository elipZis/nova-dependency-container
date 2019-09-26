# Nova Field Dependency Container
_This package is based on the work by https://github.com/epartment/nova-dependency-container_

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elipzis/nova-dependency-container.svg)](https://packagist.org/packages/elipzis/nova-dependency-container)
[![Total Downloads](https://img.shields.io/packagist/dt/elipzis/nova-dependency-container.svg)](https://packagist.org/packages/elipzis/nova-dependency-container)
[![License](https://img.shields.io/packagist/l/elipzis/nova-dependency-container.svg)](https://github.com/elipzis/nova-dependency-container/blob/master/LICENSE.md)

### Description

A container for grouping fields that depend on other field values. Dependencies can be set on any field type or value.

### Demo

![Demo](https://raw.githubusercontent.com/elipzis/nova-dependency-container/master/docs/demo.gif)

### Installation

The package can be installed through Composer.

```bash
composer require elipzis/nova-dependency-container
```

### Usage

1. Add the `elipzis\NovaDependencyContainer\HasDependencies` trait to your Nova Resource.
2. Add the `elipzis\NovaDependencyContainer\NovaDependencyContainer` to your Nova Resource `fields` method.

```php
class Page extends Resource
{
    use HasDependencies;

    public function fields(Request $request)
    {
        return [
            
            Select::make('Name format', 'name_format')->options([
                0 => 'First Name',
                1 => 'First Name / Last Name',
                2 => 'Full Name'
            ])->displayUsingLabels(),

            NovaDependencyContainer::make([
                Text::make('First Name', 'first_name')
            ])->dependsOn('name_format', '0'),

        ];
    }
}
```

### Dependencies

The package supports two kinds of dependencies:

1. `->dependsOn('field', 'value')`
2. `->dependsOnNotEmpty('field')`

These dependencies can be combined by chaining the methods on the `NovaDependencyContainer`:

```php
NovaDependencyContainer::make(...)
    ->dependsOn('field1', 'value1')
    ->dependsOnNotEmpty('field2')
    ->dependsOn('field3', 'value3')
```

The fields used as dependencies can by of any of the default Laravel Nova field types.

For example a checkbox:

![Demo](https://raw.githubusercontent.com/elipzis/nova-dependency-container/master/docs/demo-2.gif)

### License
The MIT License (MIT). Please see [License File](https://github.com/elipzis/nova-dependency-container/blob/master/LICENSE.md) for more information.
Forked from [https://github.com/epartment/nova-dependency-container](https://github.com/epartment/nova-dependency-container/).
