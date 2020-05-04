<?php

namespace chaser\server\connection;

use chaser\server\Log;
use chaser\server\reactor\Reactor;

/**
 * tcp 服务器接收连接类
 *
 * @package chaser\server\connection
 */
class Tcp extends Connection
{
    /**
     * 初始
     *
     * @var int
     */
    const STATUS_INITIAL = 1;

    /**
     * 连接
     *
     * @var int
     */
    const STATUS_CONNECTING = 2;

    /**
     * 已建立连接
     *
     * @var int
     */
    const STATUS_ESTABLISHED = 3;

    /**
     * 结束中
     *
     * @var int
     */
    const STATUS_CLOSING = 4;

    /**
     * 已结束
     *
     * @var int
     */
    const STATUS_CLOSED = 5;

    /**
     * 当前连接状态
     *
     * @var int
     */
    protected $status = self::STATUS_ESTABLISHED;

    /**
     * 读缓冲区限制
     *
     * @var int
     */
    const READ_BUFFER_SIZE = (64 << 10) - 1;

    /**
     * 接收数据缓冲限制
     *
     * @var int
     */
    const RECEIVE_BUFFER_SIZE = 10 << 10 << 10;

    /**
     * 发送数据缓冲限制
     *
     * @var int
     */
    const SEND_BUFFER_SIZE = 1 << 10 << 10;

    /**
     * 连接数统计
     *
     * @var int
     */
    protected static $idRecorder = 0;

    /**
     * 事件反应对象
     *
     * @var Reactor
     */
    protected $reactor;

    /**
     * 标记
     *
     * @var int
     */
    public $id = 0;

    /**
     * 读的字节数
     *
     * @var int
     */
    protected $readBytes = 0;

    /**
     * 写的字节数
     *
     * @var int
     */
    protected $writtenBytes = 0;

    /**
     * 接收内容缓冲
     *
     * @var string
     */
    protected $receiveBuffer = '';

    /**
     * 发送内容缓冲
     *
     * @var string
     */
    protected $sendBuffer = '';

    /**
     * 接收暂停
     *
     * @var bool
     */
    protected $receivePaused = false;

    /**
     * 初始化：监听网络
     *
     * @param Reactor $reactor
     * @param resource $socket
     * @param string $remoteAddress
     */
    public function __construct(Reactor $reactor, $socket, $remoteAddress, $app)
    {
        parent::__construct($socket, $remoteAddress, $app);

        $this->reactor = $reactor;

        $this->id = ++self::$idRecorder;

        // 非阻塞模式
        stream_set_blocking($this->socket, 0);

        // 兼容 hhvm，无缓冲
        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->socket, 0);
        }

        $this->reactor->add($this->socket, Reactor::EV_READ, [$this, 'read']);
    }

    /**
     * 读操作
     *
     * @param resource $socket
     * @param bool $checkEof
     */
    public function read($socket, $checkEof = true)
    {
        $buffer = self::input($socket);
        if ($buffer) {
            $this->readBytes += strlen($buffer);
            $this->receiveBuffer .= $buffer;
            if ($this->receivePaused) {
                return;
            }
            if (strlen($this->receiveBuffer) > self::RECEIVE_BUFFER_SIZE) {
                $this->close('Receive message up to limit!');
            } else {
                $this->response();
            }
        } elseif ($checkEof && ($buffer === false || self::closed($socket))) {
            $this->destroy();
        }
    }

    /**
     * 写操作
     *
     * @return bool
     */
    public function write()
    {
        $length = self::output($this->socket, $this->sendBuffer);
        if ($length > 0) {
            $this->writtenBytes += $length;
            if ($length === strlen($this->sendBuffer)) {
                $this->writtenBytes += $length;
                $this->reactor->del($this->socket, Reactor::EV_WRITE);
                $this->sendBuffer = '';
                // onBufferDrain：缓冲区发送完毕
                if ($this->status === self::STATUS_CLOSING) {
                    $this->destroy();
                }
                return true;
            }
            $this->sendBuffer = substr($this->sendBuffer, $length);
        } else {
            self::$statistics['send_fail']++;
            $this->destroy();
        }
    }

    /**
     * 接收响应
     */
    protected function response()
    {
        $package = $this->getPackage();

        if ($package) {

            Log::record('request:' . $package);

            self::$statistics['request']++;

            $decode = self::decode($package);

            $data = $this->app
                ? is_callable($this->app) ? call_user_func($this->app, $decode) : new $this->app($decode)
                : $decode;

            $this->send($data);
        }
    }

    /**
     * 从缓存中取包
     *
     * @return string
     */
    protected function getPackage()
    {
        $package = $this->receiveBuffer;
        $this->receiveBuffer = '';
        return $package;
    }

    /**
     * 编码响应包
     *
     * @param string $message
     * @return string
     */
    protected static function encode($message)
    {
        return $message;
    }

    /**
     * 请求包解码
     *
     * @param string $package
     * @return string
     */
    protected static function decode($package)
    {
        return $package;
    }

    /**
     * 输入数据
     *
     * @param resource $socket
     * @return bool|string
     */
    protected static function input($socket)
    {
        set_error_handler(function () {
        });
        $buffer = fread($socket, self::READ_BUFFER_SIZE);
        restore_error_handler();
        return $buffer;
    }

    /**
     * 输出数据
     *
     * @param resource $socket
     * @param string $output
     * @return bool|int
     */
    protected static function output($socket, $output)
    {
        set_error_handler(function () {
        });
        $length = fwrite($socket, $output);
        restore_error_handler();
        return $length;
    }

    /**
     * 发送信息
     *
     * @param string $data 发送数据
     * @param bool $raw 是否已处理
     * @return bool|int
     */
    public function send($data, $raw = false)
    {
        // 关闭中或已关闭不发送
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return false;
        }

        if ($raw === false) {
            $data = self::encode($data);
            if (!$data) {
                return null;
            }
        }

        if ($this->sendBuffer) {

        } else {
            // 输出信息
            $sendLength = self::output($this->socket, $data);

            if ($sendLength) {
                $this->writtenBytes += $sendLength;
                if ($sendLength === strlen($data)) {
                    return true;
                }
                $this->sendBuffer = substr($data, $sendLength);
                if (self::closed($this->socket)) {
                    self::$statistics['send_fail']++;
                    // onError: 客户端关闭
                    $this->destroy();
                    return false;
                }
                $this->reactor->add($this->socket, Reactor::EV_WRITE, [$this, 'write']);
            } else {

            }
        }


    }

    /**
     * 暂停读取数据
     *
     * @return void
     */
    public function pauseReceive()
    {
        $this->reactor->del($this->socket, Reactor::EV_READ);
        $this->receivePaused = true;
    }

    /**
     * 继续读取数据
     *
     * @return void
     */
    public function resumeReceive()
    {
        if ($this->receivePaused === true) {
            $this->reactor->add($this->socket, Reactor::EV_READ, [$this, 'read']);
            $this->receivePaused = false;
            $this->read($this->socket, false);
        }
    }

    /**
     * 判断连接状态
     *
     * @param $socket
     * @return bool
     */
    protected static function closed($socket)
    {
        return !is_resource($socket) || feof($socket);
    }

    /**
     * 关闭连接/暂停接收
     *
     * @param mixed $data
     * @param bool $raw
     * @return void
     */
    public function close($data = null, $raw = false)
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return;
        } else {
            if ($data !== null) {
                $this->send($data, $raw);
            }
            $this->status = self::STATUS_CLOSING;
        }
        $this->sendBuffer ? $this->pauseReceive() : $this->destroy();
    }

    /**
     * 破坏连接
     */
    public function destroy()
    {
        if ($this->status === self::STATUS_CLOSED) {
            return;
        }

        // 移除事件监听
        $this->reactor->del($this->socket, Reactor::EV_READ);
        $this->reactor->del($this->socket, Reactor::EV_WRITE);

        // 关闭流资源
        set_error_handler(function () {
        });
        fclose($this->socket);
        restore_error_handler();

        $this->status = self::STATUS_CLOSED;

        // onClose：关闭连接

        // 协议可能有的 onClose 事件

        // 置空事件函数/对象，卸载当前连接
        $this->sendBuffer = $this->receiveBuffer = '';
    }

    /**
     * Destruct.
     *
     * @return void
     */
    public function __destruct()
    {
//        static $mod;
//        self::$statistics['count']--;
//        if (Worker::getGracefulStop()) {
//            if (!isset($mod)) {
//                $mod = ceil((self::$statistics['connection_count'] + 1) / 3);
//            }
//
//            if (0 === self::$statistics['connection_count'] % $mod) {
//                Worker::log('worker[' . posix_getpid() . '] remains ' . self::$statistics['connection_count'] . ' connection(s)');
//            }
//
//            if(0 === self::$statistics['connection_count']) {
//                Worker::stopAll();
//            }
//        }
    }
}
