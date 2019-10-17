<?php

namespace sower\swoole\resetters;

use sower\Container;
use sower\Http;
use sower\swoole\Sandbox;

/**
 * Class RebindHttpContainer
 * @package sower\swoole\resetters
 * @property Container $app;
 */
class RebindHttpContainer implements ResetterContract
{

    public function handle(Container $app, Sandbox $sandbox)
    {
        $http = $app->make(Http::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetHttp = $closure->bindTo($http, $http);
        $resetHttp();

        return $app;
    }
}
