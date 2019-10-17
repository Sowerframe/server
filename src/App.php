<?php

namespace sower\swoole;

class App extends \sower\App
{
    public function runningInConsole()
    {
        return false;
    }
}
