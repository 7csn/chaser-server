<?php

namespace chaser\server\worker;

class Tcp extends Protocol
{
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
    public function connect($socket)
    {
    }

    public function reload()
    {
    }
}
