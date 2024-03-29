<?php

namespace sower\swoole;

use Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server\Task;
use sower\App;
use sower\console\Output;
use sower\exception\Handle;
use sower\swoole\helper\helper\Str;
use sower\swoole\App as SwooleApp;
use sower\swoole\concerns\InteractsWithSwooleTable;
use sower\swoole\concerns\InteractsWithWebsocket;
use sower\swoole\facade\Server;
use Throwable;

/**
 * Class Manager
 */
class Swoole
{
    use InteractsWithSwooleTable, InteractsWithWebsocket;

    /**
     * @var App
     */
    protected $container;

    /**
     * @var SwooleApp
     */
    protected $app;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start',
        'shutDown',
        'workerStart',
        'workerStop',
        'packet',
        'bufferFull',
        'bufferEmpty',
        'task',
        'finish',
        'pipeMessage',
        'workerError',
        'managerStart',
        'managerStop',
        'request',
    ];

    /**
     * Manager constructor.
     * @param App $container
     */
    public function __construct(App $container)
    {
        $this->container = $container;
        $this->initialize();
    }

    /**
     * Run swoole server.
     */
    public function run()
    {
        $this->container->make(Server::class)->start();
    }

    /**
     * Stop swoole server.
     */
    public function stop()
    {
        $this->container->make(Server::class)->shutdown();
    }

    /**
     * Initialize.
     */
    protected function initialize()
    {
        $this->createTables();
        $this->prepareWebsocket();
        $this->setSwooleServerListeners();
    }

    /**
     * Set swoole server listeners.
     */
    protected function setSwooleServerListeners()
    {
        foreach ($this->events as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->container->event->trigger("swoole.$event", func_get_args());
            };

            $this->container->make(Server::class)->on($event, $callback);
        }
    }

    /**
     * "onStart" listener.
     */
    public function onStart()
    {
        $this->setProcessName('master process');
        $this->createPidFile();

        $this->container->event->trigger('swoole.start', func_get_args());
    }

    /**
     * The listener of "managerStart" event.
     *
     * @return void
     */
    public function onManagerStart()
    {
        $this->setProcessName('manager process');
        $this->container->event->trigger('swoole.managerStart', func_get_args());
    }

    /**
     * "onWorkerStart" listener.
     *
     * @param \Swoole\Http\Server|mixed $server
     *
     * @throws Exception
     */
    public function onWorkerStart($server)
    {
        if ($this->container->config->get('swoole.enable_coroutine', false)) {
            Runtime::enableCoroutine(true);
        }

        $this->clearCache();

        $this->container->event->trigger('swoole.workerStart', func_get_args());

        // don't init app in task workers
        if ($server->taskworker) {
            $this->setProcessName('task process');

            return;
        }

        $this->setProcessName('worker process');

        $this->prepareApplication();

        if ($this->isServerWebsocket) {
            $this->prepareWebsocketHandler();
            $this->loadWebsocketRoutes();
        }
    }

    protected function prepareApplication()
    {
        if (!$this->app instanceof SwooleApp) {
            $this->app = new SwooleApp();
            $this->app->initialize();
        }

        $this->bindSandbox();
        $this->bindSwooleTable();

        if ($this->isServerWebsocket) {
            $this->bindRoom();
            $this->bindWebsocket();
        }
    }

    protected function prepareRequest(Request $req)
    {
        $header = $req->header ?: [];
        $server = $req->server ?: [];

        if (isset($header['x-requested-with'])) {
            $server['HTTP_X_REQUESTED_WITH'] = $header['x-requested-with'];
        }

        if (isset($header['referer'])) {
            $server['http_referer'] = $header['referer'];
        }

        if (isset($header['host'])) {
            $server['http_host'] = $header['host'];
        }

        // 重新实例化请求对象 处理swoole请求数据
        /** @var \sower\Request $request */
        $request = $this->app->make('request', [], true);

        return $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->get ?: [])
            ->withPost($req->post ?: [])
            ->withCookie($req->cookie ?: [])
            ->withInput($req->rawContent())
            ->withFiles($req->files ?: [])
            ->setBaseUrl($req->server['request_uri'])
            ->setUrl($req->server['request_uri'] . (!empty($req->server['query_string']) ? '&' . $req->server['query_string'] : ''))
            ->setPathinfo(ltrim($req->server['path_info'], '/'));
    }

    protected function sendResponse(Sandbox $sandbox, \sower\Response $sowerResponse, \Swoole\Http\Response $swooleResponse)
    {

        // 发送Header
        foreach ($sowerResponse->getHeader() as $key => $val) {
            $swooleResponse->header($key, $val);
        }

        // 发送状态码
        $swooleResponse->status($sowerResponse->getCode());

        foreach ($sandbox->getApplication()->cookie->getCookie() as $name => $val) {
            list($value, $expire, $option) = $val;

            $swooleResponse->cookie($name, $value, $expire, $option['path'], $option['domain'], $option['secure'] ? true : false, $option['httponly'] ? true : false);
        }

        $content = $sowerResponse->getContent();

        if (!empty($content)) {
            $swooleResponse->write($content);
        }

        $swooleResponse->end();
    }

    /**
     * "onRequest" listener.
     *
     * @param Request  $req
     * @param Response $res
     */
    public function onRequest($req, $res)
    {
        $this->app->event->trigger('swoole.request');

        $this->resetOnRequest();

        /** @var Sandbox $sandbox */
        $sandbox = $this->app->make(Sandbox::class);
        $request = $this->prepareRequest($req);

        try {
            $sandbox->setRequest($request);

            $sandbox->init();

            $response = $sandbox->run($request);
            $this->sendResponse($sandbox, $response, $res);
        } catch (Throwable $e) {
            try {
                $exceptionResponse = $this->app->make(Handle::class)->render($request, $e);
                
                $this->sendResponse($sandbox, $exceptionResponse, $res);
            } catch (Throwable $e) {
                $this->logServerError($e);
            }
        } finally {
            $sandbox->clear();
        }
    }

    /**
     * Reset on every request.
     */
    protected function resetOnRequest()
    {
        // Reset websocket data
        if ($this->isServerWebsocket) {
            $this->app->make(Websocket::class)->reset(true);
        }
    }

    /**
     * Set onTask listener.
     *
     * @param mixed       $server
     * @param string|Task $taskId or $task
     * @param string      $srcWorkerId
     * @param mixed       $data
     */
    public function onTask($server, $taskId, $srcWorkerId, $data)
    {
        $this->container->event->trigger('swoole.task', func_get_args());

        try {
            // push websocket message
            if ($this->isWebsocketPushPayload($data)) {
                $this->pushMessage($server, $data['data']);
                // push async task to queue
            }
        } catch (Throwable $e) {
            $this->logServerError($e);
        }
    }

    /**
     * Set onFinish listener.
     *
     * @param mixed  $server
     * @param string $taskId
     * @param mixed  $data
     */
    public function onFinish($server, $taskId, $data)
    {
        // task worker callback
        $this->container->event->trigger('swoole.finish', func_get_args());

        return;
    }

    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->removePidFile();
    }

    /**
     * Bind sandbox to Laravel app container.
     */
    protected function bindSandbox()
    {
        $this->app->bind(Sandbox::class, function (App $app) {
            return new Sandbox($app);
        });

        $this->app->bind('swoole.sandbox', Sandbox::class);
    }

    /**
     * Gets pid file path.
     *
     * @return string
     */
    protected function getPidFile()
    {
        return $this->container->make('config')->get('swoole.server.options.pid_file');
    }

    /**
     * Create pid file.
     */
    protected function createPidFile()
    {
        $pidFile = $this->getPidFile();
        $pid     = $this->container->make(Server::class)->master_pid;

        file_put_contents($pidFile, $pid);
    }

    /**
     * Remove pid file.
     */
    protected function removePidFile()
    {
        $pidFile = $this->getPidFile();

        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }

    /**
     * Clear APC or OPCache.
     */
    protected function clearCache()
    {
        if (extension_loaded('apc')) {
            apc_clear_cache();
        }

        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }

    /**
     * Set process name.
     *
     * @codeCoverageIgnore
     *
     * @param $process
     */
    protected function setProcessName($process)
    {
        // Mac OSX不支持进程重命名
        if (stristr(PHP_OS, 'DAR')) {
            return;
        }

        $serverName = 'swoole_http_server';
        $appName    = $this->container->config->get('app.name', 'sowerPHP');

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        swoole_set_process_name($name);
    }

    /**
     * Add process to http server
     *
     * @param Process $process
     */
    public function addProcess(Process $process): void
    {
        $this->container->make(Server::class)->addProcess($process);
    }

    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public function logServerError(Throwable $e)
    {
        /** @var Handle $handle */
        $handle = $this->app->make(Handle::class);

        $handle->renderForConsole(new Output(), $e);

        $handle->report($e);
    }
}
