<?php

namespace sower\swoole\resetters;

use sower\Container;
use sower\swoole\Sandbox;

class ResetEvent implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $app->instance('event', clone $sandbox->getEvent());

        return $app;
    }
}
