<?php

namespace sower\swoole\coroutine\connector;

use Exception;
use PDOException;
use sower\swoole\helper\helper\Str;
use sower\swoole\coroutine\PDO;

class Mysql extends \sower\db\connector\Mysql
{
    protected $builderClassName = \sower\db\builder\Mysql::class;

    protected function createPdo($dsn, $username, $password, $params)
    {
        return new PDO($dsn, $username, $password, $params);
    }

    /**
     * 是否断线
     * @access protected
     * @param PDOException|Exception $e 异常对象
     * @return bool
     */
    protected function isBreak($e): bool
    {
        if (!$this->config['break_reconnect']) {
            return false;
        }

        return parent::isBreak($e) || Str::contains($e->getMessage(), 'is closed');
    }
}
