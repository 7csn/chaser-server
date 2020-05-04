<?php

namespace chaser\server\connection;

use chaser\server\Log;

/**
 * http 服务器接收连接类
 *
 * @package chaser\server\connection
 */
class Http extends Tcp
{
    /**
     * 请求 uri 信息长度限制
     *
     * @var int
     */
    protected static $requestUriLimit = 1 << 12;

    /**
     * 请求头信息长度限制
     *
     * @var int
     */
    protected static $requestHeaderLimit = 4 << 10;

    /**
     * 解出包长度
     *
     * @var int
     */
    protected $packetSize = 0;

    /**
     * 取包
     *
     * @return bool|string|void
     */
    protected function getPackage()
    {
        if ($this->packetSize > 0) {
            return $this->getPackageBySize();
        }

        $header = $this->getBeforeNeedle("\r\n\r\n");
        if ($header) {
            $explode = explode("\r\n", $header);
            if (count($explode) >= 2 && self::checkFirstLine($explode[0]) && self::checkSecondLine($explode[1])) {
                $this->packetSize = self::getRequestSize($header);
                if ($this->packetSize) {
                    return $this->getPackageBySize();
                }
            }
            $this->badRequest();
        } else {
            $receiveBufferLength = strlen($this->receiveBuffer);

            if ($receiveBufferLength > self::$requestHeaderLimit) {
                return $this->outOfLimit('header');
            }

            if ($this->getNeedleIndex()) {
                if (!self::checkFirstLine($this->getBeforeNeedle())) {
                    return $this->badRequest();
                }
            } elseif ($receiveBufferLength > self::$requestUriLimit) {
                return $this->outOfLimit();
            }
        }
    }

    /**
     * 根据长度取包
     *
     * @return bool|string
     */
    protected function getPackageBySize()
    {
        if (strlen($this->receiveBuffer) >= $this->packetSize) {
            $package = substr($this->receiveBuffer, 0, $this->packetSize);
            $this->receiveBuffer = substr($this->receiveBuffer, $this->packetSize);
            $this->packetSize = 0;
            return $package;
        }
    }

    /**
     * 检测全首行格式
     *
     * @param string $data
     * @return int
     */
    protected static function checkFirstLine(string $data)
    {
        return preg_match('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) \/.*? HTTP\/[\d\.]+$/i', $data);
    }

    /**
     * 检测全次行格式
     *
     * @param string $data
     * @return int
     */
    protected static function checkSecondLine(string $data)
    {
        return preg_match('/^Host: [a-z\d\.]+(:[1-9]\d{0,4})?$/i', $data);
    }

    /**
     * 获取指定字符串位置
     *
     * @param string $needle
     * @return bool|int
     */
    protected function getNeedleIndex(string $needle = "\r\n")
    {
        return strpos($this->receiveBuffer, $needle);
    }

    /**
     * 获取指定字符串前内容
     *
     * @param string $needle
     * @return string
     */
    protected function getBeforeNeedle(string $needle = "\r\n")
    {
        return strstr($this->receiveBuffer, $needle, true);
    }

    /**
     * 通过头信息分析出数据长度
     *
     * @param string $header 头信息
     * @return int
     */
    protected static function getRequestSize($header)
    {
        // 不含请求主体的长度
        $minLength = strlen($header) + 4;

        // 请求方法
        $method = strstr($header, ' ', true);

        // GET、OPTIONS、HEAD 请求忽略主体
        if (in_array($method, ['GET', 'OPTIONS', 'HEAD'])) {
            return $minLength;
        }

        // 请求主体长度判断
        $match = [];
        if (preg_match("/\r\nContent-Length: ?(\d+)/i", $header, $match) > 0) {
            return $minLength + $match[1];
        }

        // 主体长度申明：PUT、PATCH、POST 必须；DELETE 可无
        return $method === 'DELETE' ? $minLength : 0;
    }

    protected static function encode($message)
    {
        return parent::encode($message);
    }

    protected static function decode($package)
    {
        return parent::decode($package);
    }

    /**
     * 坏的请求包
     */
    protected function badRequest()
    {
        $this->close("HTTP/1.1 400 Bad Request\r\n\r\n", true);
    }

    /**
     * 请求超限制
     *
     * @param string $message
     */
    protected function outOfLimit($message = 'uri')
    {
        $this->close("HTTP/1.1 414 Request-" . $message . " too long\r\n\r\n", true);
    }
}
