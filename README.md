# pine3ree ParamsResolver

[![Continuous Integration](https://github.com/pine3ree/pine3ree-params-resolver/actions/workflows/continuos-integration.yml/badge.svg)](https://github.com/pine3ree/pine3ree-params-resolver/actions/workflows/continuos-integration.yml)

ParamsResolver is an utility service that uses reflection to resolve parameters
for a given callable performing look-up in the following order and matched
against class/interface/parameter names:

- injected parameters,
- dependencies or parameters in the injected or composed container
- default values id available in the callable

Example:

In the following example the dependency $db is fetched from the container, the
`$config` parameter is provided and the `$options` parameter receives the default
empty array value.

```php
<?php

use My\Db;
use pine3ree\Container\ParamsResolver;

class MyDataMapper
{
    public function__construct(My\Db $db, array $config, array $options = [])
    {
        //...
    }
    //...
}

//...
// include the container container
//...

$paramsResolver = new ParamsResolver($container);

$args = $paramsResolver->resolve(MyDataMapper::class, '__construct', [
    'config' => [
        //..
    ],
]);

$myDataMapper = new MyDataMapper(...args);

```

A common usage of ParamsResolver is within a reflection based factory
