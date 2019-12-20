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
namespace Memorandum\Cache;


use Memorandum\Memorandum;

abstract class Base
{
    /**
     * Returns a unique key based on the function name and the arguments.
     *
     * @param Memorandum $class
     * @param array $args
     * @return string
     */
    public function key(Memorandum $class, array $args): string
    {
        return sha1($class->getName() . ':' . serialize($args));
    }

    /**
     * Return true if the current cache is still valid.
     *
     * @param array $files
     * @return bool
     */
    public function isCacheValid(array $files): bool
    {
        foreach ($files as $file => $time) {
            if (!is_file($file) && !is_dir($file) || $time < filemtime($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the cache content by a key.
     *
     * If the cache is not found or it is not longer valid, and empty string
     * is expected.
     *
     * @param string $key
     * @return string
     */
    abstract public function get(string $key): string;

    /**
     * Persists a new cache storing the content and the list of files (and their modified time).
     *
     * @param string $key
     * @param array $files
     * @param string $content
     *
     * @return bool
     */
    abstract public function persist(string $key, array $files, string $content): bool;
}
