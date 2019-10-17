<?php

namespace sower\swoole\resetters;

use sower\Container;
use sower\swoole\Sandbox;

class ResetConfig implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $app->instance('config', clone $sandbox->getConfig());

        return $app;
    }
}
