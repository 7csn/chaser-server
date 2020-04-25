<?php

namespace chaser\server\worker;

use chaser\server\Master;

/**
 * 基于网络传输协议的抽象工作类
 *
 * @package chaser\server\worker
 */
abstract class Protocol extends Worker
{
    /**
     * 默认挂起连接数量上限
     */
    const BACK_LOG = 102400;

    /**
     * 监听目标（地址/文件）
     *
     * @var string
     */
    protected $target;

    /**
     * 端口复用（单独通知员工工作）
     *
     * @var bool
     */
    protected $reusePort = false;

    /**
     * 资源流上下文
     *
     * @var resource
     */
    protected $context;

    /**
     * 监听网络
     *
     * @var resource
     */
    protected $socket;

    /**
     * 监听网络初始化错误号
     *
     * @var int
     */
    protected $errorNumber = 0;

    /**
     * 监听网络初始化错误消息
     *
     * @var string
     */
    protected $errorMessage = '';

    /**
     * 监听网络标志组合
     *
     * @var int
     */
    protected $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

    /**
     * 接收连接开关
     *
     * @var bool
     */
    protected $acceptConnection = false;

    /**
     * 通讯协议
     *
     * @return string
     */
    abstract protected static function transport();

    /**
     * 构造函数
     *
     * @param Master $master
     * @param string $target
     */
    public function __construct(Master $master, string $target, array $options = [], bool $reusePort = false)
    {
        parent::__construct($master);

        $this->target = $target;

        $this->listening = $this->transport() . '://' . $this->target;

        // 创建上下文绑定
        if (empty($options['socket']['backlog'])) {
            $options['socket']['backlog'] = self::BACK_LOG;
        }
        $this->context = stream_context_create($options);

        // 端口不复用，监听网络继承（子进程继承父进程工作模板）
        ($this->reusePort = $reusePort) || $this->listen();
    }

    /**
     * 初始化
     */
    public function initialize()
    {
    }

    /**
     * 监听网络
     */
    protected function listen()
    {
        $this->createSocket();
        $this->resumeAccept();
    }

    /**
     * 移除监听网络
     */
    protected function unListen()
    {
        $this->pauseAccept();
        $this->removeSocket();
    }

    /**
     * 创建监听网络
     */
    protected function createSocket()
    {
        if (!$this->socket) {
            if ($this->reusePort) {
                stream_context_set_option($this->context, 'socket', 'so_reuseport', 1);
            }
            $this->socket = stream_socket_server($this->listening, $this->errorNumber, $this->errorMessage, $this->flags, $this->context);
            if ($this->socket) {
                $this->socketSettings();
            } else {
                exit("create_socket[{$this->remit()}://{$this->target}]:" . $this->errorMessage);
            }
        }
    }

    /**
     * 清除监听网络
     */
    protected function removeSocket()
    {
        if ($this->socket) {
            set_error_handler(function () {
            });
            fclose($this->socket);
            restore_error_handler();
            $this->socket = null;
        }
    }

    /**
     * 接受新连接
     */
    protected function resumeAccept()
    {
        if (!$this->acceptConnection && $this->socket) {

            $this->acceptConnection = true;
        }
    }

    /**
     * 停止接受新连接
     */
    protected function pauseAccept()
    {
        if ($this->acceptConnection && $this->socket) {

            $this->acceptConnection = false;
        }
    }

    /**
     * 资源流设置
     */
    protected function socketSettings()
    {
        // 非阻塞模式
        stream_set_blocking($this->socket, 0);
    }

    /**
     * 析构：移除网络监听
     */
    public function __destruct()
    {
        $this->unListen();
    }
}
