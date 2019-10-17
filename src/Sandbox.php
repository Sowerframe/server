<?php

namespace sower\swoole;

use RuntimeException;
use sower\App;
use sower\Config;
use sower\Container;
use sower\Event;
use sower\Http;
use sower\Request;
use sower\Response;
use sower\swoole\coroutine\Context;
use sower\swoole\resetters\BindRequest;
use sower\swoole\resetters\ClearInstances;
use sower\swoole\resetters\RebindHttpContainer;
use sower\swoole\resetters\RebindRouterContainer;
use sower\swoole\resetters\ResetConfig;
use sower\swoole\resetters\ResetDumper;
use sower\swoole\resetters\ResetEvent;
use sower\swoole\resetters\ResetterContract;

class Sandbox
{
    /** @var App */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Event */
    protected $event;

    protected $resetters = [];

    public function __construct($app = null)
    {
        if (!$app instanceof Container) {
            return;
        }

        $this->setBaseApp($app);
        $this->initialize();
    }

    public function setBaseApp(Container $app)
    {
        $this->app = $app;

        return $this;
    }

    public function getBaseApp()
    {
        return $this->app;
    }

    protected function initialize()
    {
        if (!$this->app instanceof Container) {
            throw new RuntimeException('A base app has not been set.');
        }

        Container::setInstance(function () {
            return $this->getApplication();
        });

        $this->setInitialConfig();
        $this->setInitialEvent();
        $this->setInitialResetters();

        return $this;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function run(Request $request)
    {

        $level = ob_get_level();
        ob_start();

        $response = $this->handleRequest($request);

        $content = $response->getContent();

        if (ob_get_level() == 0) {
            ob_start();
        }

        $this->getHttp()->end($response);

        if (ob_get_length() > 0) {
            $response->content(ob_get_contents() . $content);
        }

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        return $response;
    }

    protected function handleRequest(Request $request)
    {
        return $this->getHttp()->run($request);
    }

    public function init()
    {
        if (!$this->config instanceof Config) {
            throw new RuntimeException('Please initialize after setting base app.');
        }

        $this->setInstance($app = $this->getApplication());
        $this->resetApp($app);
    }

    public function clear()
    {
        Context::clear();
        $this->setInstance($this->getBaseApp());
    }

    /**
     * @return Http
     */
    protected function getHttp()
    {
        return $this->getApplication()->make(Http::class);
    }

    public function getApplication()
    {
        $snapshot = $this->getSnapshot();
        if ($snapshot instanceof Container) {
            return $snapshot;
        }

        $snapshot = clone $this->getBaseApp();
        $this->setSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * Get current snapshot.
     */
    public function getSnapshot()
    {
        return Context::getApp();
    }

    public function setSnapshot(Container $snapshot)
    {
        Context::setApp($snapshot);

        return $this;
    }

    public function setInstance(Container $app)
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        Context::setApp($app);
    }

    /**
     * Set initial config.
     */
    protected function setInitialConfig()
    {
        $this->config = clone $this->getBaseApp()->config;
    }

    protected function setInitialEvent()
    {
        $this->event = clone $this->getBaseApp()->event;
    }

    /**
     * Get config snapshot.
     */
    public function getConfig()
    {
        return $this->config;
    }

    public function getEvent()
    {
        return $this->event;
    }

    /**
     * Initialize resetters.
     */
    protected function setInitialResetters()
    {
        $app = $this->getBaseApp();

        $resetters = [
            ClearInstances::class,
            RebindHttpContainer::class,
            RebindRouterContainer::class,
            BindRequest::class,
            ResetDumper::class,
            ResetConfig::class,
            ResetEvent::class,
        ];

        $resetters = array_merge($resetters, $this->config->get('swoole.resetters', []));

        foreach ($resetters as $resetter) {
            $resetterClass = $app->make($resetter);
            if (!$resetterClass instanceof ResetterContract) {
                throw new RuntimeException("{$resetter} must implement " . ResetterContract::class);
            }
            $this->resetters[$resetter] = $resetterClass;
        }
    }

    /**
     * Get Initialized resetters.
     */
    public function getResetters()
    {
        return $this->resetters;
    }

    /**
     * Reset Application.
     *
     * @param Container $app
     */
    public function resetApp(Container $app)
    {
        foreach ($this->resetters as $resetter) {
            $resetter->handle($app, $this);
        }
    }

    public function setRequest(Request $request)
    {
        Context::setData('_request', $request);

        return $this;
    }

    /**
     * Get current request.
     */
    public function getRequest()
    {
        return Context::getData('_request');
    }
}
