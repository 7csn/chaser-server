<?php

namespace chaser\server\connection;

/**
 * frame 服务器接收连接类
 *
 * @package chaser\server\connection
 */
class Frame extends Tcp
{
    /**
     * 解析包长度
     *
     * @return int|bool|null
     */
    protected function getPacketSize()
    {
        if (strlen($this->receiveBuffer) >= 4) {
            return unpack('Nlength', $this->receiveBuffer)['length'] ?: false;
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
        return pack('N', 4 + strlen($message)) . $message;
    }

    /**
     * 解码
     *
     * @param string $package
     * @return string
     */
    protected function decode($package)
    {
        return substr($package, 4);
    }
}
