<?php

namespace sower\swoole\facade;

use sower\Facade;

class Server extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.server';
    }
}
