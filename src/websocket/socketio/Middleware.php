<?php

namespace sower\swoole\websocket\socketio;

use Closure;
use sower\Request;
use sower\Response;

class Middleware
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);
        $origin   = $request->header('origin');
        if ($origin) {
            $response->header([
                'Access-Control-Allow-Origin'      => $origin,
                'Access-Control-Allow-Credentials' => 'true',
                'X-XSS-Protection'                 => 0,
            ]);
        } else {
            $response->header([
                'Access-Control-Allow-Origin' => '*',
            ]);
        }
        return $response;
    }
}
