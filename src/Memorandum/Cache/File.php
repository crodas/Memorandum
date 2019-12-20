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


use RuntimeException;

class File extends Base
{
    protected $directory;

    protected $cache = [];

    public function __construct($directory)
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true)) {
                throw new RuntimeException("Cannot create a directory $directory");
            }
        }

        $this->directory = $directory;
    }

    protected function getCachePath(string $key): string
    {
        return $this->directory . '/' . $key . '.php';
    }

    public function get(string $key): string
    {
        $file  = $this->getCachePath($key);
        $cache = null;

        if (isset($this->cache[$key])) {
            $cache = $this->cache[$key];
        } else if (is_file($file)) {
            $cache = require $file;

            $this->cache[$key] = $cache;
        }

        if ($cache && $this->isCacheValid($cache['files'])) {
            return $cache['content'];
        }

        return '';
    }

    public function persist(string $key, array $files, string $content): bool
    {
        $this->cache[$key] = compact('files', 'content');

        $code = var_export(compact('files', 'content'), true);
        $temp = tempnam(sys_get_temp_dir(), 'memo');
        file_put_contents($temp, "<?php return " . $code . ";");

        rename($temp, $this->getCachePath($key));

        return true;
    }
}
