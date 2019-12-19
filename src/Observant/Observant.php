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
namespace Observant;

use Observant\Cache\Base;
use Observant\Cache\Memory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionFunction;
use RuntimeException;
use ReflectionObject;
use SplFileInfo;
use Closure;

class Observant
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
    protected static $cache;

    /**
     * @var array
     */
    protected $files = [];

    public function __construct(callable $function)
    {
        $this->function = $function;
        $this->name     = $this->parseFunctionName($function);

        if (! self::$cache) {
            self::setCache(new Memory());
        }
    }

    /**
     * Sets the cache storage implementation.
     *
     * @param Base $cache
     */
    public static function setCache(Base $cache)
    {
        self::$cache = $cache;
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
    protected function parseFunctionName(callable $function): string
    {
        if (is_object($function)) {
            $reflection = new ReflectionObject($function);
        } else {
            $reflection = new ReflectionFunction($function);
        }

        if ($function instanceof Closure) {
            $this->function = $function->bindTo($this);

            return sha1(
                $reflection->getFileName()
                . ':'. $reflection->getStartLine()
                . '-' . $reflection->getEndLine()
            );
        }

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
            if (!is_file($arg) && !is_dir($arg)) {
                continue;
            }

            $file = new SplFileInfo($arg);
            $files[] = $file->getRealPath();

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
     * Returns a list of files associated with the current function call.
     *
     * This is only accessible while a function is being called (through __invoke).
     *
     * A list of files found in the arguments from the current function is returned.
     *
     * @return mixed
     */
    public function getFiles()
    {
        if (empty($this->files)) {
            throw new RuntimeException('Invalid calls to getFiles');
        }
        return end($this->files);
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

    public function __invoke(... $args)
    {
        $cacheKey = self::$cache->key($this, $args);

        if ($return = self::$cache->get($cacheKey)) {
            return unserialize($return);
        }

        $function = $this->function;
        $files    = $this->getFilesFromArgs($args);

        $this->files[] = array_keys($files);
        $return  = $function(...$args);
        array_pop($this->files);

        self::$cache->persist($cacheKey, $files, serialize($return));

        return $return;
    }
}
