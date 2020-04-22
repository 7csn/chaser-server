<?php

namespace chaser\server\worker;

use chaser\server\Master;
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
     * 职权范围
     *
     * @return string
     */
    abstract public static function remit();

    /**
     * 初始化
     *
     * @return mixed
     */
    abstract public function initialize();

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
     */
    public function __construct(Master $master)
    {
        $this->master = $master;
    }

    /**
     * 批量配置属性
     *
     * @param array $settings 配置数组
     * @return $this
     */
    public function settings(array $settings = [])
    {
        array_walk($settings, function ($value, $name) {
            $this->{$name} = $value;
        });

        return $this;
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
}
