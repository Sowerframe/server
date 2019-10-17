<?php

namespace sower\swoole\facade;

use sower\Facade;

class Table extends Facade
{
    protected static function getFacadeClass()
    {
        return 'swoole.table';
    }
}
