<?php

namespace sower\swoole\resetters;

use sower\Container;
use sower\Request;
use sower\swoole\Sandbox;

class BindRequest implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $request = $sandbox->getRequest();

        if ($request instanceof Request) {
            $app->instance('request', $request);
        }

        return $app;
    }
}
