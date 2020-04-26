<?php

namespace chaser\server\reactor;

/**
 * 事件反应器接口
 *
 * @package chaser\server\reactor
 */
abstract class Reactor
{
    /**
     * 读取事件
     *
     * @var int
     */
    const EV_READ = 1;

    /**
     * 写入事件
     *
     * @var int
     */
    const EV_WRITE = 2;

    /**
     * 除了事件
     *
     * @var int
     */
    const EV_EXCEPT = 3;

    /**
     * 信号事件
     *
     * @var int
     */
    const EV_SIGNAL = 4;

    /**
     * 将事件侦听器添加到事件循环
     *
     * @param mixed $fd 流
     * @param int $flag 事件类型
     * @param callable|array $func 回调方法
     * @param array $args 回调参数
     * @return mixed
     */
    abstract public function add($fd, int $flag, $func, array $args = []);

    /**
     * 从事件循环中移除事件侦听器
     *
     * @param mixed $fd 流
     * @param int $flag 事件类型
     * @return mixed
     */
    abstract public function del($fd, int $flag);

    /**
     * 主回路
     *
     * @return mixed
     */
    abstract public function loop();
}
