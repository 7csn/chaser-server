<?php

namespace chaser\server\connection;

/**
 * 服务器接收连接抽象类
 *
 * @package chaser\server\connection
 */
abstract class Connection
{
    /**
     * 统计信息
     *
     * @var array
     */
    public static $statistics = [
        'count' => 0,
        'request' => 0,
        'exception' => 0,
        'send_fail' => 0,
    ];

    /**
     * 连接资源流
     *
     * @var resource
     */
    protected $socket;

    /**
     * 远程地址
     *
     * @var string
     */
    protected $remoteAddress = '';

    /**
     * 应用类型
     *
     * @var string
     */
    protected $app;

    /**
     * 发送信息
     *
     * @param string $data
     * @return mixed
     */
    abstract public function send($data);

    /**
     * 关闭连接
     *
     * @return void
     */
    abstract public function close();

    /**
     * 初始化
     *
     * @param $socket
     * @param string $remoteAddress
     * @param string $app
     */
    protected function __construct($socket, $remoteAddress = '', $app)
    {
        $this->socket = $socket;
        $this->remoteAddress = $remoteAddress;
        $this->app = $app;
        self::$statistics['count']++;
    }

    /**
     * 获取远程 IP
     *
     * @return string
     */
    public function getRemoteIp()
    {
        return $this->remoteAddress
            ? strstr($this->remoteAddress, ':', true)
            : '';
    }

    /**
     * 获取远程端口号
     *
     * @return int
     */
    public function getRemotePort()
    {
        return $this->remoteAddress
            ? (int)substr(strstr($this->remoteAddress, ':'), 1)
            : 0;
    }

    /**
     * 获取远程地址
     *
     * @return string
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }

    /**
     * 获取本地 IP
     *
     * @return string
     */
    public function getLocalIp()
    {
        $address = $this->getLocalAddress();
        return $address ? strstr($address, ':', true) : '';
    }

    /**
     * 获取本地端口号
     *
     * @return int
     */
    public function getLocalPort()
    {
        $address = $this->getLocalAddress();
        return $address ? (int)substr(strstr($address, ':'), 1) : 0;
    }

    /**
     * 获取本地地址
     *
     * @return string
     */
    public function getLocalAddress()
    {
        return (string)@stream_socket_get_name($this->socket, false);
    }

    /**
     * 是否 ipv4
     *
     * return bool.
     */
    public function isIpV4()
    {
        return strpos($this->getRemoteIp(), ':') === false;
    }

    /**
     * 是否 ipv6
     *
     * return bool.
     */
    public function isIpV6()
    {
        return !$this->isIpV4();
    }
}
