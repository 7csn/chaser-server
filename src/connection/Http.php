<?php

namespace chaser\server\connection;

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
     * 状态码列表
     *
     * @var array
     */
    public static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    ];

    /**
     * 解析包长度
     *
     * @return int|false|null
     */
    protected function getPacketSize()
    {
        $header = $this->getBeforeNeedle("\r\n\r\n");
        if ($header) {
            $explode = explode("\r\n", $header);
            if (count($explode) >= 2 && self::checkFirstLine($explode[0]) && self::checkSecondLine($explode[1])) {
                $packetSize = self::getRequestSize($header);
                if ($packetSize) {
                    return $packetSize;
                }
            }
            return $this->error(400);
        } else {
            $receiveBufferLength = strlen($this->receiveBuffer);

            if ($receiveBufferLength > self::$requestHeaderLimit) {
                return $this->error(413);
            }

            if ($this->getNeedleIndex()) {
                if (!self::checkFirstLine($this->getBeforeNeedle())) {
                    return $this->error(400);
                }
            } elseif ($receiveBufferLength > self::$requestUriLimit) {
                return $this->error(414);
            }
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
     * 解包错误
     *
     * @param int $code
     * @return bool
     */
    protected function error($code)
    {
        $message = sprintf("HTTP/1.1 %d %s\r\n\r\n", $code, self::$codes[$code]);
        $this->send($message, true);
        return false;
    }
}
