<?php

namespace chaser\server\reactor;

/**
 * 备用事件循环类
 *
 * @package chaser\server\reactor
 */
class Select extends Reactor
{
    /**
     * 等待读取的流数组
     *
     * @var array
     */
    protected $readFds = [];

    /**
     * 等待写入的流数组
     *
     * @var array
     */
    protected $writeFds = [];

    /**
     * 等待带外数据的流数组
     *
     * @var array
     */
    protected $exceptFds = [];

    /**
     * 侦听事件集合
     *
     * @var array
     */
    protected $allEvents = [];

    /**
     * 等待事件时限（微秒）
     *
     * @var int
     */
    protected $selectTimeout = 1e8;

    /**
     * 添加事件侦听器
     *
     * @param mixed $fd 流|信号
     * @param int $flag 事件类型
     * @param callable|array $func 回调方法
     * @param array $args 回调参数
     * @return bool
     */
    public function add($fd, int $flag, $func, array $args = [])
    {
        switch ($flag) {
            case self::EV_READ:
                if (count($this->readFds) >= 1024) {
                    echo("Upper limit 1024 connections" . PHP_EOL);
                }
                $this->addTo('read', $fd, $flag, $func);
                break;
            case self::EV_WRITE:
                if (count($this->writeFds) >= 1024) {
                    echo("Upper limit 1024 connections" . PHP_EOL);
                }
                $this->addTo('write', $fd, $flag, $func);
                break;
            case self::EV_EXCEPT:
                $this->addTo('except', $fd, $flag, $func);
                break;
            case self::EV_SIGNAL:
                $intFd = (int)$fd;
                $this->allEvents[$intFd][$flag] = [$func, $fd];
                pcntl_signal($fd, [$this, 'signalHandler']);
                break;
        }
        return true;
    }

    /**
     * 移除事件侦听器
     *
     * @param mixed $fd 流|信号
     * @param int $flag 事件类型
     * @return bool
     */
    public function del($fd, int $flag)
    {
        switch ($flag) {
            case static::EV_READ:
                return $this->delTo('read', $fd, $flag);
            case static::EV_WRITE:
                return $this->delTo('write', $fd, $flag);
            case static::EV_EXCEPT:
                return $this->delTo('except', $fd, $flag);
            case self::EV_SIGNAL:
                unset($this->allEvents[$fd]);
                pcntl_signal($fd, SIG_IGN);
                break;
        }
        return false;

    }

    /**
     * 主回路
     */
    public function loop()
    {
        while (1) {
            // 调用等待信号的处理器
            pcntl_signal_dispatch();

            // 等待时间流数组：过渡变量，防止原数组被修改
            $readFds = $this->readFds;
            $writeFds = $this->writeFds;
            $exceptFds = $this->exceptFds;

            // 等待事件发生：可读、可写、带外数据、信号
            set_error_handler(function () {
            });
            $amount = stream_select($readFds, $writeFds, $exceptFds, 0, $this->selectTimeout);
            restore_error_handler();

            // 事件侦听回调
            if ($amount > 0) {
                $readFds && $this->callback($readFds, static::EV_READ);
                $writeFds && $this->callback($writeFds, static::EV_WRITE);
                $exceptFds && $this->callback($exceptFds, static::EV_EXCEPT);
            }
        }
    }

    /**
     * 添加事件侦听器
     *
     * @param string $name 类型
     * @param resource $fd 流
     * @param int $flag 事件类型
     * @param callable $func 回调方法
     * @return bool
     */
    protected function addTo($name, $fd, $flag, $func)
    {
        $intFd = (int)$fd;
        $this->allEvents[$intFd][$flag] = [$func, $fd];
        $this->{$name . 'Fds'}[$intFd] = $fd;
    }

    /**
     * 移除事件侦听器
     *
     * @param string $name 类型
     * @param resource $fd 流
     * @param int $flag 事件类型
     * @return bool
     */
    protected function delTo($name, $fd, $flag)
    {
        $intFd = (int)$fd;
        unset($this->allEvents[$intFd][$flag], $this->{$name . 'Fds'}[$intFd]);
        if (empty($this->allEvents[$intFd])) {
            unset($this->allEvents[$intFd]);
        }
        return true;
    }

    /**
     * 流数组事件回调
     *
     * @param array $fds 流数组
     * @param int $flag 事件类型
     */
    protected function callback($fds, $flag)
    {
        foreach ($fds as $fd) {
            $intFd = (int)$fd;
            if (key_exists($flag, $this->allEvents[$intFd])) {
                $this->allEvents[$intFd][$flag][0]($this->allEvents[$intFd][$flag][1]);
            }
        }
    }

    /**
     * 信号处理器
     *
     * @param int $signal
     */
    public function signalHandler($signal)
    {
        call_user_func($this->allEvents[$signal][self::EV_SIGNAL][0], $signal);
    }
}
