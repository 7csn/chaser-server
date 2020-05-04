<?php

namespace chaser\server\worker;

use chaser\server\connection\Frame as FrameConnection;

/**
 * 基于 frame 协议的工作类
 *
 * @package chaser\server\worker
 */
class Frame extends Tcp
{
    /**
     * 职权范围
     *
     * @return string
     */
    public static function remit()
    {
        return 'frame';
    }

    /**
     * 获取客户端连接对象
     *
     * @param resource $socket
     * @param string $peerName
     * @return FrameConnection
     */
    protected function getConnection($socket, $peerName)
    {
        return new FrameConnection($this->reactor, $socket, $peerName, $this->app);
    }
}
