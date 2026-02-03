# Rector OpenAPI Annotations to Attributes

This package provides Rector rules to automatically convert OpenAPI annotations to PHP attributes.

## Features

This package includes two Rector rules:

1. **AddMissingOAAnnotationImportRector** - Adds missing `use OpenApi\Annotations as OA;` import when OpenAPI annotations are used
2. **OpenApiAnnotationToAttributeRector** - Converts OpenAPI annotations to PHP 8 attributes

## Installation

Clone repository into a separate directory:

```bash
git clone git@github.com:pincher2012/rector-swagger-php.git
```

## Configuration


Install dependencies using composer 

```
composer install 
```

Add the rules to `rector.php` configuration file:

If project contains issues with unimported annotations, you can add this rule

```php
<?php

use Rector\Config\RectorConfig;
use Rector\OpenApi\AddMissingOAAnnotationImportRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rules([
        AddMissingOAAnnotationImportRector::class,
    ]);
};
```

Then use following configuration

```php
<?php

use Rector\Config\RectorConfig;
use Rector\OpenApi\OpenApiAnnotationToAttributeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rules([
        OpenApiAnnotationToAttributeRector::class,
    ]);
};
```

[Or use config/rector.php](config/rector.php)

## Run rector

```bash
vendor/bin/rector process /path/to/project/src --config=/path/to/config/rector.php
```
## Examples

### Before

```php
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(schema="Order")
 */
class Order {}
```

### After

```php
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Order')]
class Order {}
```

## Testing

The package includes comprehensive tests in the `rules-tests/` directory, with various fixture files covering different use cases and edge cases.
Feel free to contribute more test fixtures or improve existing ones.