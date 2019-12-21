<?php

use Memorandum\Memorandum;

class MemorandumTest extends PHPUnit\Framework\TestCase
{
    public static function getCallback()
    {
        $function = function() {
            return random_int(1, 0xfffffff);
        };

        $function1 = static function() {
            return random_int(1, 0xfffffff);
        };

        return [
            [$function],
            [$function1],
            [new Klass()],
            ['expensive'],
            ['Klass::expensive'],
            [['Klass', 'expensive']],
            [[new Klass(), 'expensive']]
        ];
    }

    public static function getCallbackWithArgument()
    {
        $function = function($p) {
            return $p . ':' . random_int(1, 0xfffffff);
        };

        $args = [];

        for ($i = 0; $i < 100; ++$i) {
            $args[] = [$function, $i];
        }

        return $args;
    }

    /**
     * @dataProvider getCallback
     */
    public function testMemoization($function)
    {
        $function = memo($function);
        $expected = $function();

        for($i=0; $i < 1000; ++$i) {
            $this->assertEquals($expected, $function());
        }
    }

    /**
     * @dataProvider getCallbackWithArgument
     */
    public function testMemoizationWithArgs($function, $i)
    {
        $function = memo($function);
        $expected = $function($i);
        for ($e = 0; $e < 100; ++$e) {
            $this->assertEquals($expected, $function($i));
        }
    }

    public function testMemoizationWithArgsAndFiles()
    {
        $self = $this;
        $function = memo(function($p) use($self) {
            $self->assertTrue(is_array($this->getFiles()));
            return $p . ':' . random_int(1, 0xfffffff);
        });

        $expected = $function(__DIR__);
        $this->assertEquals($expected, $function(__DIR__));
        $this->assertEquals($expected, $function(__DIR__));

        $tmp = __DIR__ . '/' . uniqid();

        touch($tmp, time() + 1);
        touch(dirname($tmp), time() + 1);

        $this->assertNotEquals($expected, $last = $function(__DIR__));

        unlink($tmp);
        $this->assertNotEquals($expected, $lastlast = $function(__DIR__));
        $this->assertNotEquals($last, $function(__DIR__));
        $this->assertEquals($lastlast, $function(__DIR__));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testInvalidCallsToGetFilesCall()
    {
        $x = Memorandum::wrap(function() {});
        $x->getFiles();
    }

    /**
     * @expectedException RuntimeException
     */
    public function testInvalidCurrentCall()
    {
        Memorandum::current();
    }

    public function testCurrentCall()
    {
        $self = $this;
        memorandum(function() use($self) {
            memo(function() use ($self) {
                $self->assertEquals($this, Memorandum::current());
            })();
            $self->assertEquals($this, Memorandum::current());
        })();
    }

    public static function getCallbackBindingCustomFile()
    {
        $file = __DIR__ . '/fixture/lock';

        $function = function() use ($file) {
            $this->watchFile(__DIR__  . '/fixture');

            return random_int(1, 0xfffffff);
        };

        return [
            [memo($function, new \Memorandum\Cache\File(__DIR__ . '/tmp/')), $file, time() + 10],
            [memo($function, new \Memorandum\Cache\File(__DIR__ . '/tmp/')), $file, time() + 20],
        ];
    }

    /**
     * @dataProvider getCallbackBindingCustomFile
     */
    public function testWatchCustomFiles($function, $file, $time)
    {
        touch($file, $time);
        touch(dirname($file), $time);

        $first = $function();

        for ($i=0; $i < 100; ++$i) {
            $this->assertEquals($first, $function());
        }

        unlink($file);
        $second = $function();

        $this->assertNotEquals($second, $first);

        for ($i=0; $i < 100; ++$i) {
            $this->assertEquals($second, $function());
        }
    }

    public function testFunctionSameInstance()
    {
        $f = function() {};

        $this->assertEquals(
            spl_object_hash(memo($f)),
            spl_object_hash(memo($f))
        );
    }

    public function testFunctionDifferentInstanceWithCustomCache()
    {
        $f = function() {};

        $c1 = new \Memorandum\Cache\File(__DIR__ . '/tmp/xxx');
        $c2 = new \Memorandum\Cache\File(__DIR__ . '/tmp/xxx');

        $this->assertNotEquals(
            spl_object_hash(memo($f, $c1)),
            spl_object_hash(memo($f, $c2))
        );
    }
}
