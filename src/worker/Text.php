<?php

namespace chaser\server\worker;

use chaser\server\connection\Text as TextConnection;

/**
 * 基于 text 协议的工作类
 *
 * @package chaser\server\worker
 */
class Text extends Tcp
{
    /**
     * 职权范围
     *
     * @return string
     */
    public static function remit()
    {
        return 'text';
    }

    /**
     * 获取客户端连接对象
     *
     * @param resource $socket
     * @param string $peerName
     * @return TextConnection
     */
    protected function getConnection($socket, $peerName)
    {
        return new TextConnection($this->reactor, $socket, $peerName, $this->app);
    }
}
