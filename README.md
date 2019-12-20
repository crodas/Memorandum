# Memorandum

A magic [memoization](https://en.wikipedia.org/wiki/Memoization) function on steroids.

## Motivation

[Memoization](https://en.wikipedia.org/wiki/Memoization) function are useful to avoid doing repetitive tasks over and over.

This library stores the output of a function. If any of the function's argument are files or folders, the output will be deemed
as valid until any of those files or folders are either modified or removed.

Any function which involves I/O is destined to be slow, that's why Observant speed up things to processing things only when
needed.

## Usage


```php
<?php

$function = memo(function($file) {
    sleep(10);
    return 'cached ouptut:' . $file;
});

$result = $function(__DIR__);
$result = $function(__DIR__);
// The function would be called once, and the output
// will be remembered until a new file is added to __DIR__
// or another file is removed.
```
