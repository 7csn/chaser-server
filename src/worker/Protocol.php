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
     * 监听目标（地址/文件）
     *
     * @var string
     */
    protected $target;

    /**
     * 构造函数
     *
     * @param Master $master
     * @param string $target
     */
    public function __construct(Master $master, string $target)
    {
        parent::__construct($master);

        $this->target = $target;
    }

    /**
     * 初始化
     */
    public function initialize()
    {
    }
}
