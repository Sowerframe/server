<?php

namespace sower\swoole\resetters;

use sower\Container;
use sower\swoole\Sandbox;

interface ResetterContract
{
    /**
     * "handle" function for resetting app.
     *
     * @param Container $app
     * @param Sandbox   $sandbox
     */
    public function handle(Container $app, Sandbox $sandbox);
}
