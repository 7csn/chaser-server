<?php

namespace chaser\server\worker;

/**
 * 基于 http 协议的工作类
 *
 * @package chaser\server\worker
 */
class Http extends Protocol
{
    /**
     * 职权范围
     *
     * @return string
     */
    public static function remit()
    {
        return 'http';
    }

    /**
     * 通讯协议
     *
     * @return string
     */
    protected static function transport()
    {
        return 'tcp';
    }

    public function reload()
    {
    }
}
