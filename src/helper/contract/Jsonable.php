<?php declare(strict_types=1);
#coding: utf-8
# +-------------------------------------------------------------------
# | Swoole InteractsWithWebsocket
# +-------------------------------------------------------------------
# | Copyright (c) 2017-2019 Sower rights reserved.
# +-------------------------------------------------------------------
# +-------------------------------------------------------------------
namespace sower\swoole\helper\contract;

interface Jsonable
{
    public function toJson($options = 0);
}
