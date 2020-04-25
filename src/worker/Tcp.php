<?php

namespace chaser\server\worker;


class Tcp extends Protocol
{
    /**
     * 职权范围
     *
     * @return string
     */
    public static function remit()
    {
        return 'tcp';
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
