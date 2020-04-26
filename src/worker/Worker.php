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
    }
}
