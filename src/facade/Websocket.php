<?php

namespace sower\swoole\facade;

use sower\Facade;

class Websocket extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.websocket';
    }
}
