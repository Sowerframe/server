<?php

namespace sower\swoole\facade;

use sower\Facade;

class Room extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.room';
    }
}
