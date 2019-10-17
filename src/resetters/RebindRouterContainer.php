<?php

namespace sower\swoole\resetters;

use sower\Container;
use sower\Route;
use sower\swoole\Sandbox;

class RebindRouterContainer implements ResetterContract
{

    protected $container;

    /**
     * @var mixed
     */
    protected $routes;

    public function handle(Container $app, Sandbox $sandbox)
    {
        $route = $app->make(Route::class);

        $closure = function () use ($app) {
            $this->app = $app;
        };

        $resetRouter = $closure->bindTo($route, $route);
        $resetRouter();

        return $app;
    }
}
