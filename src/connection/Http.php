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
     * 头信息缓存
     *
     * @var array
     */
    protected $headers = [];

    /**
     * 是否压缩
     *
     * @var bool
     */
    protected $gzip = false;

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
        return preg_match('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) \/.*? HTTP\/[\d\.]+$/', $data);
    }

    /**
     * 检测全次行格式
     *
     * @param string $data
     * @return int
     */
    protected static function checkSecondLine(string $data)
    {
        return preg_match('/^Host: ?[a-z\d\.]+(:[1-9]\d{0,4})?$/', $data);
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

    /**
     * 编码
     *
     * @param string $content
     * @return string
     */
    protected function encode($content)
    {
        if (!isset($this->headers['Http-Code'])) {
            $header = "HTTP/1.1 200 OK\r\n";
        } else {
            $header = $this->headers['Http-Code'] . "\r\n";
            unset($this->headers['Http-Code']);
        }

        if (!isset($this->headers['Content-Type'])) {
            $header .= "Content-Type: text/html;charset=utf-8\r\n";
        }

        foreach ($this->headers as $key => $item) {
            if ('Set-Cookie' === $key && is_array($item)) {
                foreach ($item as $it) {
                    $header .= $it . "\r\n";
                }
            } else {
                $header .= $item . "\r\n";
            }
        }
        if ($this->gzip && isset($this->gzip) && $this->gzip) {
            $header .= "Content-Encoding: gzip\r\n";
            $content = gzencode($content, $this->gzip);
        }

        $header .= "Server: chaser/1.0.0\r\nContent-Length: " . strlen($content) . "\r\n\r\n";

        return $header . $content;
    }

    /**
     * 解码：重置超全局数组
     *
     * @param string $package
     * @return array
     */
    protected function decode($package)
    {
        $get = $post = $cookie = $request = $files = $session = $server = [];

        // 清除头缓存
        $this->headers = ['Connection' => 'Connection: keep-alive'];

        list($header, $body) = explode("\r\n\r\n", $package, 2);

        $lines = explode("\r\n", $header);

        $server = [
            'SERVER_SOFTWARE' => 'chaser/1.0.0',
            'QUERY_STRING' => '',
            'CONTENT_TYPE' => '',
            'SERVER_NAME' => '',
            'HTTP_HOST' => '',
            'HTTP_USER_AGENT' => '',
            'HTTP_ACCEPT' => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => '',
            'HTTP_CONNECTION' => ''
        ];

        // 方法、资源、协议
        list($server['REQUEST_METHOD'], $server['REQUEST_URI'], $server['SERVER_PROTOCOL']) = explode(' ',
            array_shift($lines));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            list($key, $value) = explode(':', $line, 2);

            $key = str_replace('-', '_', strtoupper($key));
            $value = trim($value);

            $server['HTTP_' . $key] = $value;

            switch ($key) {
                case 'HOST':
                    $server['SERVER_NAME'] = strstr($value, ':', true) ?: $value;
                    break;
                case 'COOKIE':
                    parse_str(str_replace('; ', '&', $server['HTTP_COOKIE']), $cookie);
                    break;
                case 'CONTENT_TYPE':
                    if (preg_match('/boundary="?(\S+)"?/', $value, $match)) {
                        $server['CONTENT_TYPE'] = 'multipart/form-data';
                        if ($server['REQUEST_METHOD'] === 'POST') {
                            $files = self::parseUploadFiles($body, '--' . $match[1], $post);
                        }
                    } else {
                        $server['CONTENT_TYPE'] = strstr($value, ';', true) ?: $value;
                        switch ($server['CONTENT_TYPE']) {
                            case 'application/json':
                                if ($server['REQUEST_METHOD'] === 'POST') {
                                    $post = json_decode($body, true);
                                } elseif ($server['REQUEST_METHOD'] !== 'GET') {
                                    $request = json_decode($body, true);
                                }
                                break;
                            case 'application/x-www-form-urlencoded':
                                if ($server['REQUEST_METHOD'] === 'POST') {
                                    parse_str($body, $post);
                                } elseif ($server['REQUEST_METHOD'] !== 'GET') {
                                    parse_str($body, $request);
                                }
                                break;
                        }
                    }
                    break;
                case 'CONTENT_LENGTH':
                    $server['CONTENT_LENGTH'] = $value;
                    break;
                case 'UPGRADE':
//                    if ($value == 'websocket') {
//                        $connection->protocol = "\\Workerman\\Protocols\\Websocket";
//                        return \Workerman\Protocols\Websocket::input($package, $connection);
//                    }
                    break;
            }
        }

        if (strpos($server['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            $this->gzip = true;
        }

        // 原始请求数据
        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $body;

        // QUERY_STRING
        $server['QUERY_STRING'] = parse_url($server['REQUEST_URI'], PHP_URL_QUERY);
        if ($server['QUERY_STRING']) {
            parse_str($server['QUERY_STRING'], $get);
        }

        // 整合请求参数，优先级：POST > 类 POST > GET > COOKIE
        $request = array_merge($cookie, $get, $request, $post);

        // 本地：IP、端口号
        $server['SERVER_ADDR'] = $this->getLocalIp();
        $server['SERVER_PORT'] = $this->getLocalPort();

        // 远程：IP、端口号
        $server['REMOTE_ADDR'] = $this->getRemoteIp();
        $server['REMOTE_PORT'] = $this->getRemotePort();

        // 时间
        $server['REQUEST_TIME'] = (int)$server['REQUEST_TIME_FLOAT'] = microtime(true);

        return [
            '_GET' => $get,
            '_POST' => $post,
            '_COOKIE' => $cookie,
            '_REQUEST' => $request,
            '_FILES' => $files,
            '_SESSION' => $session,
            '_SERVER' => $server
        ];
    }

    /**
     * 解析上传文件数组
     *
     * @param string $body
     * @param string $boundary
     * @param array $post
     * @return array
     */
    protected static function parseUploadFiles($body, $boundary, &$post)
    {
        $files = [];

        // 文件上传内容列表
        $body = substr($body, 0, -4 - strlen($boundary));
        $dataList = explode("{$boundary}\r\n", $body);

        // 首个为空则移除
        $dataList[0] === '' && array_shift($dataList);


        foreach ($dataList as $index => $data) {

            list($dataHeaderList, $dataBody) = explode("\r\n\r\n", $data, 2);

            // 移除尾部 \r\n
            $dataBody = substr($dataBody, 0, -2);

            foreach (explode("\r\n", $dataHeaderList) as $dataHeader) {

                list($headerKey, $headerValue) = explode(": ", $dataHeader);

                switch (strtolower($headerKey)) {
                    case "content-disposition":
                        // 文件数据
                        if (preg_match('/name="(.*?)"; filename="(.*?)"$/', $headerValue, $match)) {
                            $files[$index] = [
                                'name' => $match[1],
                                'file_name' => $match[2],
                                'file_data' => $dataBody,
                                'file_size' => strlen($dataBody),
                            ];
                        }// POST 数据
                        elseif (preg_match('/name="(.*?)"$/', $headerValue, $match)) {
                            $post[$match[1]] = $dataBody;
                        }
                        break;
                    case "content-type":
                        $files[$index]['file_type'] = trim($headerValue);
                        break;
                }
            }
        }

        return $files;
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
