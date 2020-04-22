<?php

namespace chaser\server\worker;

/**
 * 基于 http 协议的工作类
 *
 * @package chaser\server\worker
 */
class Http extends Protocol
{
    public static function remit()
    {
        return 'http';
    }

    public function reload()
    {
        // TODO: Implement reload() method.
    }
}
