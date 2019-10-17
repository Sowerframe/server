<?php

namespace sower\swoole;

use Swoole\Http\Server as HttpServer;
use Swoole\Websocket\Server as WebsocketServer;
use sower\App;
use sower\event\RouteLoaded;
use sower\Route;
use sower\swoole\command\Server as ServerCommand;
use sower\swoole\facade\Server;
use sower\swoole\websocket\socketio\Controller;
use sower\swoole\websocket\socketio\Middleware;

class Service extends \sower\Service
{
    protected $isWebsocket = false;

    /**
     * @var HttpServer | WebsocketServer
     */
    protected static $server;

    public function register()
    {
        $this->isWebsocket = $this->app->config->get('swoole.websocket.enabled', false);

        $this->app->bind(Server::class, function () {
            if (is_null(static::$server)) {
                $this->createSwooleServer();
            }

            return static::$server;
        });

        $this->app->bind('swoole.server', Server::class);

        $this->app->bind(Swoole::class, function (App $app) {
            return new Swoole($app);
        });

        $this->app->bind('swoole', Swoole::class);
    }

    public function boot()
    {
        $this->commands(ServerCommand::class);
        if ($this->isWebsocket) {
            $this->app->event->listen(RouteLoaded::class, function (Route $route) {
                $route->group(function () use ($route) {
                    $route->get('socket.io/', '@upgrade');
                    $route->post('socket.io/', '@reject');
                })->prefix(Controller::class)->middleware(Middleware::class);
            });
        }
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $server     = $this->isWebsocket ? WebsocketServer::class : HttpServer::class;
        $config     = $this->app->config;
        $host       = $config->get('swoole.server.host');
        $port       = $config->get('swoole.server.port');
        $socketType = $config->get('swoole.server.socket_type', SWOOLE_SOCK_TCP);
        $mode       = $config->get('swoole.server.mode', SWOOLE_PROCESS);

        static::$server = new $server($host, $port, $mode, $socketType);

        $options = $config->get('swoole.server.options');

        static::$server->set($options);
    }
}
