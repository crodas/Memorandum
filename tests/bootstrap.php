<?php

require __DIR__ . '/../vendor/autoload.php';

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

foreach (glob(__DIR__ . '/tmp/*') as $file) {
    if (is_dir($file)) {
        rmdir($file);
    } else {
        unlink($file);
    }
}
