# Memorandum

A magic [memoization](https://en.wikipedia.org/wiki/Memoization) function on steroids.

## Motivation

[Memoization](https://en.wikipedia.org/wiki/Memoization) stores the results of expensive function calls and
returns the cached result when the same inputs occur again.

This library stores the result of a function. If any of the function's argument are files or folders, the result will
be deemed as valid until any of those files or folders are either modified or removed.

Any function which involves I/O is destined to be slow, that's why Memorandum speeds up things by processing things only when
necessary.

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
