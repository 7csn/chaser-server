<?php

namespace chaser\server\connection;

/**
 * text 服务器接收连接类
 *
 * @package chaser\server\connection
 */
class Text extends Tcp
{
    /**
     * 解析包长度
     *
     * @return int|null
     */
    protected function getPacketSize()
    {
        $index = strpos($this->receiveBuffer, "\n");
        if ($index !== false) {
            return $index + 1;
        }
    }

    /**
     * 编码
     *
     * @param string $message
     * @return string
     */
    protected function encode($message)
    {
        return $message . "\r\n";
    }

    /**
     * 解码
     *
     * @param string $package
     * @return string
     */
    protected function decode($package)
    {
        return rtrim($package, "\r\n");
    }
}
