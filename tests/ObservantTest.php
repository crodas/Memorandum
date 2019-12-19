<?php


class ObservantTest extends PHPUnit\Framework\TestCase
{
    public static function getCallback()
    {
        $function = observant(function() {
            return random_int(1, 0xfffffff);
        });

        return [
            [$function]
        ];
    }
    public static function getCallbackWithArgument()
    {
        $function = observant(function($p) {
            return $p . ':' . random_int(1, 0xfffffff);
        });

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

        $expected = $function($i);
        for ($e = 0; $e < 100; ++$e) {
            $this->assertEquals($expected, $function($i));
        }
    }

    public function testMemoizationWithArgsAndFiles()
    {
        $function = observant(function($p) {
            return $p . ':' . random_int(1, 0xfffffff);
        });

        $expected = $function(__DIR__);
        $this->assertEquals($expected, $function(__DIR__));
        sleep(1);
        $this->assertEquals($expected, $function(__DIR__));

        $tmp = __DIR__ . '/' . uniqid();

        sleep(1);
        touch($tmp);
        $this->assertNotEquals($expected, $function(__DIR__));

        unlink($tmp);
        $this->assertNotEquals($expected, $function(__DIR__));
    }
}
