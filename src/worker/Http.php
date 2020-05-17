<?php

namespace chaser\server\worker;

use chaser\server\connection\Http as HttpConnection;
use chaser\server\reactor\Reactor;

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
     * 构造函数
     *
     * @param Reactor $reactor
     * @param string $target
     * @param array $route
     * @param null $app
     * @param array $options
     * @param bool $reusePort
     * @param string $name
     * @param int $count
     */
    public function __construct(
        Reactor $reactor,
        $target,
        array $route = [],
        $app = null,
        array $options = [],
        $reusePort = false,
        $name = 'none',
        $count = 1
    ) {
        parent::__construct($reactor, $target, $app, $options, $reusePort, $name, $count);
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
        return new HttpConnection($this->reactor, $socket, $peerName, $this->app);
    }
}
