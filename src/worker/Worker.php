<?php

namespace chaser\server\worker;

use chaser\server\Master;
use chaser\server\reactor\Reactor;
use Countable;

/**
 * 抽象工作类
 *
 * @package chaser\server\worker
 */
abstract class Worker implements Countable
{
    /**
     * 管理对象
     *
     * @var Master
     */
    protected $master;

    /**
     * 职员数量
     *
     * @var int
     */
    protected $count = 1;

    /**
     * 监听网络
     *
     * @var string
     */
    protected $listening = 'none';

    /**
     * 全局事件反应器
     *
     * @var Reactor
     */
    protected $reactor;

    /**
     * 职权范围
     *
     * @return string
     */
    abstract public static function remit();

    /**
     * 重载配置事件
     *
     * @return mixed
     */
    abstract public function reload();

    /**
     * 添加管理
     *
     * @param Master $master
     * @param Reactor $reactor
     */
    public function __construct(Master $master, Reactor $reactor)
    {
        $this->master = $master;
        $this->reactor = $reactor;
    }

    /**
     * 职员数量
     *
     * @return int
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * 开始工作
     */
    public function run()
    {
        $this->master->setCmdTitle("Chaser[{$this->remit()}]：$this->listening");

        $this->reinstallSignal();
    }

    /**
     * 信号处理程序
     *
     * @param int $signal
     */
    public function signalHandler($signal)
    {
        switch ($signal) {
            case SIGINT:    // 停止
            case SIGTERM:   // 优雅停止
            case SIGQUIT:   // 重载
            case SIGUSR1:   // 优雅重载
                break;
            case SIGUSR2:   // 状态
                break;
            case SIGIO:     // 连接状态
                break;
        }
    }

    /**
     * 重装信号处理器
     */
    protected function reinstallSignal()
    {
        // 终止、优雅终止、重载、优雅重载、状态、连接状态
        $signals = [SIGINT, SIGTERM, SIGUSR1, SIGQUIT, SIGUSR2, SIGIO];
        foreach ($signals as $signal) {
            // 移除旧信号处理
            pcntl_signal($signal, SIG_IGN, false);
            // 重装信号处理器
            $this->reactor->add($signal, Reactor::EV_SIGNAL, [$this, 'signalHandler']);
        }
    }
}
