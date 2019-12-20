<?php


function expensive() {
    return random_int(1, 10000);
}

class Klass
{
    public static function expensive()
    {
        return random_int(1, 10000);
    }

    public function __invoke()
    {
        return random_int(1, 10000);
    }
}


class ObservantTest extends PHPUnit\Framework\TestCase
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
        $function = observant($function);
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
        $function = observant($function);
        $expected = $function($i);
        for ($e = 0; $e < 100; ++$e) {
            $this->assertEquals($expected, $function($i));
        }
    }

    public function testMemoizationWithArgsAndFiles()
    {
        $self = $this;
        $function = observant(function($p) use($self) {
            $self->assertTrue(is_array($this->getFiles()));
            return $p . ':' . random_int(1, 0xfffffff);
        });

        $expected = $function(__DIR__);
        $this->assertEquals($expected, $function(__DIR__));
        sleep(1);
        $this->assertEquals($expected, $function(__DIR__));

        $tmp = __DIR__ . '/' . uniqid();

        sleep(1);
        touch($tmp);
        $this->assertNotEquals($expected, $last = $function(__DIR__));

        unlink($tmp);
        $this->assertNotEquals($expected, $lastlast = $function(__DIR__));
        $this->assertNotEquals($last, $function(__DIR__));
        $this->assertEquals($lastlast, $function(__DIR__));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testInvalidCallsToGetFiles()
    {
        $x = new \Observant\Observant(function() {});
        $x->getFiles();
    }

    public function testWatchCustomFiles()
    {
        $file = __DIR__ . '/fixture/lock';

        $function = observant(function() use ($file) {
            touch($file);
            $this->watchFile(__DIR__  . '/fixture');

            return random_int(1, 0xfffffff);
        });

        $first = $function();

        for ($i=0; $i < 100; ++$i) {
            $this->assertEquals($first, $function());
        }

        unlink($file);

        for ($i=0; $i < 100; ++$i) {
            $this->assertNotEquals($first, $function());
        }
    }
}
