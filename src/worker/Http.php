<?php

namespace chaser\server\worker;

use chaser\server\connection\Http as HttpConnection;

/**
 * 基于 http 协议的工作类
 *
 * @package chaser\server\worker
 */
class Http extends Tcp
{
    /**
     * 职权范围
     *
     * @return string
     */
    public static function remit()
    {
        return 'http';
    }

    /**
     * 获取客户端连接对
     *
     * @param resource $socket
     * @param string $peerName
     * @return HttpConnection
     */
    protected function getConnection($socket, $peerName)
    {
        return new HttpConnection($this, $socket, $peerName);
    }
}
