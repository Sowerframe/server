<?php
// +----------------------------------------------------------------------
// | sowerPHP [ WE CAN DO IT JUST sower IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://sowerphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace sower\swoole\websocket;

use Swoole\Websocket\Frame;
use sower\Request;

interface HandlerContract
{
    /**
     * "onOpen" listener.
     *
     * @param int     $fd
     * @param Request $request
     */
    public function onOpen($fd, Request $request);

    /**
     * "onMessage" listener.
     *  only triggered when event handler not found
     *
     * @param Frame $frame
     */
    public function onMessage(Frame $frame);

    /**
     * "onClose" listener.
     *
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose($fd, $reactorId);
}
