<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2020 César D. Rodas                                               |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/
namespace Memorandum;

use Memorandum\Cache\Base;
use Memorandum\Cache\Memory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use RuntimeException;
use ReflectionObject;
use SplFileInfo;
use Closure;
use Throwable;

class Memorandum
{
    /**
     * @var callable
     */
    protected $function;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Base
     */
    protected static $globalCache;

    /**
     * @var Base
     */
    protected $cache;

    /**
     * @var array
     */
    private static $instances = [];

    /**
     * @var array
     */
    protected $files = [];

    private function __construct(string $name, callable $function, Base $cache = null)
    {
        $this->function = $function;
        $this->cache    = $cache;
        $this->name     = $name;

        if ($function instanceof Closure) {
            try {
                // Attempt to bind the closure's this to this Memorandum object.
                $this->function = $function->bindTo($this);
            } catch(Throwable $e) {
            }
        }


        if (! self::$globalCache) {
            self::setGlobalCache(new Memory());
        }
    }

    /**
     * Sets the cache storage implementation.
     *
     * @param Base $cache
     */
    public static function setGlobalCache(Base $cache)
    {
        self::$globalCache = $cache;
    }

    /**
     * Gets the function name.
     *
     * Usually getting the name through the reflection would be enough, but closures are
     * unnamed functions, and we need a consistent way of naming all functions.
     *
     * @param callable $function
     * @return string
     * @throws \ReflectionException
     */
    protected static function parseFunctionName(callable $function): string
    {
        if ($function instanceof Closure) {
            $reflection = new ReflectionFunction($function);

            return sha1(
                $reflection->getFileName()
                . ':'. $reflection->getStartLine()
                . '-' . $reflection->getEndLine()
            );
        } else if (is_array($function) || is_string($function) && strpos($function, '::') > 0) {
            // No further checks are needed, because PHP is awesome and 'Callable'
            // already makes sure the callback is valid.
            list($class, $method) = is_array($function) ? $function : explode("::", $function, 2);

            $reflection = (new ReflectionClass($class))->getMethod($method);

            return $reflection->getDeclaringClass()->getName()
                . '::' . $reflection->getName();
        }

        $reflection = is_object($function)
            ? new ReflectionObject($function)
            : new ReflectionFunction($function);

        return $reflection->getName();
    }

    /**
     * Returns a list of files, and their modified time from a list of arguments
     *
     * Returns a list of files (and their mod times) from an array of arguments. This
     * list of files and mtime is later used by the cache engine to know if the cached
     * return is still relevant or not.
     *
     * If a directory is found, it will be walked recursively to know better if the cache
     * should be invalidated when a file is added or removed to a certain directory.
     *
     * @param array $args
     * @return array
     */
    private function getFilesFromArgs(array $args): array
    {
        $files = [];
        foreach ($args as $arg) {
            $files = array_merge($files, $this->getAllFiles($arg));
        }

        $files = array_unique($files);
        sort($files);

        $filesModifies = [];

        foreach ($files as $file) {
            $filesModifies[$file] = filemtime($file);
        }


        return $filesModifies;
    }

    /**
     * Returns an array of files.
     *
     * This function would return an array of files based on the input. If the input is an directory,
     * every file inside would be included.
     *
     * @param $file
     * @return array
     */
    protected function getAllFiles($file): array
    {
        if (!is_file($file) && !is_dir($file)) {
            return [];
        }

        $file = new SplFileInfo($file);
        $files = [$file->getRealPath()];

        if ($file->isDir()) {
            $iterator = new RecursiveDirectoryIterator(
                $file->getPathname(),
                RecursiveDirectoryIterator::SKIP_DOTS
            );
            $iterator = new RecursiveIteratorIterator($iterator);

            foreach ($iterator as $file) {
                $files[] = dirname($file->getRealPath());
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    /**
     * Makes sure the current call comes within the __invoke() context.
     *
     * @throws RuntimeException
     */
    protected function isWithinInvokeContext()
    {
        if (empty($this->files)) {
            throw new RuntimeException('Invalid calls to getFiles');
        }
    }

    /**
     * Returns a list of files associated with the current function call.
     *
     * This is only accessible while a function is being called (through __invoke).
     *
     * A list of files found in the arguments from the current function is returned.
     *
     * @return array
     */
    public function getFiles(): array
    {
        $this->isWithinInvokeContext();
        return array_keys(end($this->files));
    }

    /**
     * Binds a file to the current cache.
     *
     * The 'gist' of Memorandum is that the cache is bound to files that may exists in the argument.
     *
     * The cache is treated as valid as long as none of the watched files changes.
     *
     * This function allow to watch any extra file that is not defined in the argument.
     *
     * @param $file
     */
    public function watchFile($file)
    {
        $this->isWithinInvokeContext();

        $id = count($this->files) - 1;
        foreach ($this->getAllFiles($file) as $file) {
            $this->files[$id][$file] = filemtime($file);
        }
    }

    /**
     * Returns the current Memorandum instance. This function will throw an exception if it is called
     * outside of __invoke().
     *
     * The idea is to provide the current object to non closures functions, same way it is available as $this
     * in closures.
     *
     * @return Memorandum
     *
     * @throws RuntimeException
     */
    public static function current(): Memorandum
    {
        if (empty(self::$instances)) {
            throw new RuntimeException('Invalid call to instance()');
        }

        return end(self::$instances);
    }

    /**
     * Returns the wrapped function name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Calls the underlying function or return the cached output.
     *
     * @param mixed ...$args
     * @return mixed|string
     */
    public function __invoke(... $args)
    {
        $storage  = $this->cache ?: self::$globalCache;
        $cacheKey = $storage->key($this, $args);

        if ($return = $storage->get($cacheKey)) {
            return unserialize($return);
        }

        $function = $this->function;
        $files    = $this->getFilesFromArgs($args);

        $this->files[]     = $files;
        self::$instances[] = $this;

        $return  = $function(...$args);

        $files = array_pop($this->files);
        array_pop(self::$instances);

        $storage->persist($cacheKey, $files, serialize($return));

        return $return;

    }

    /**
     * Creates a new Memorandum instance.
     *
     * The instances are unique per function and cache layer.
     *
     * @param callable $function
     * @param Base|null $cache
     * @return Memorandum
     */
    public static function wrap(Callable $function, Base $cache = null): Memorandum
    {
        static $instances = [];

        $name = self::parseFunctionName($function);
        $id = $name . ':' . ($cache ? spl_object_hash($cache) : 'default');

        if (!isset($instances[$id])) {
            $instances[$id] = new Memorandum($name, $function, $cache);
        }

        return $instances[$id];
    }
}
