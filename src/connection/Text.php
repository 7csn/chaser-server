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
     * 取包
     *
     * @return bool|string
     */
    protected function getPackage()
    {
        $index = strpos($this->receiveBuffer, "\n");
        if ($index !== false) {
            $package = substr($this->receiveBuffer, 0, $index);
            $this->receiveBuffer = substr($this->receiveBuffer, $index + 1);
            return $package;
        }
    }

    /**
     * 编码
     *
     * @param string $message
     * @return string
     */
    protected static function encode($message)
    {
        return $message . "\r\n";
    }

    /**
     * 解码
     *
     * @param string $package
     * @return string
     */
    protected static function decode($package)
    {
        return rtrim($package, "\r\n");
    }
}
