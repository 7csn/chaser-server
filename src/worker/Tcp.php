<?php

namespace chaser\server\worker;

use chaser\server\connection\Tcp as TcpConnection;

class Tcp extends Protocol
{
    /**
     * 接收连接对象数组
     *
     * @var array
     */
    protected $connections = [];

    /**
     * 职权范围
     *
     * @return string
     */
    public static function remit()
    {
        return 'tcp';
    }

    /**
     * 通讯协议
     *
     * @return string
     */
    protected static function transport()
    {
        return 'tcp';
    }

    /**
     * 资源流设置
     */
    protected function socketSettings()
    {
        if (function_exists('socket_import_stream')) {
            set_error_handler(function () {
            });
            // 转化底层 socket
            $socket = socket_import_stream($this->socket);
            // 开启连接状态检测
            socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            // 禁用 tcp 的 Nagle 算法，允许小包数据发送
            socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
            restore_error_handler();
        }
        parent::socketSettings();
    }

    /**
     * 产生新连接
     *
     * @param resource $socket 流
     */
    public function acceptConnection($socket)
    {
        $connection = $this->getConnection(...$this->acceptSocket($socket));
//        $this->connections[$connection->id] = $connection;
    }

    /**
     * 获取客户端连接对象
     *
     * @param resource $socket
     * @param string $peerName
     * @return TcpConnection
     */
    protected function getConnection($socket, $peerName)
    {
        return new TcpConnection($this, $socket, $peerName);
    }

    /**
     * 接收流资源信息
     *
     * @param resource $socket
     * @return array
     */
    protected function acceptSocket(resource $socket)
    {
        set_error_handler(function () {
        });
        $acceptSocket = stream_socket_accept($socket, 0, $peerName);
        restore_error_handler();
        return [$acceptSocket, $peerName];
    }
}
