<?php

namespace chaser\server;

/**
 * 日志类
 *
 * @package chaser\server
 */
class Log
{
    /**
     * 日志：调试模式
     *
     * @var int
     */
    const LEVEL_DEBUG = 1;

    /**
     * 日志：信息模式
     *
     * @var int
     */
    const LEVEL_INFO = 2;

    /**
     * 日志：警告模式
     *
     * @var int
     */
    const LEVEL_WARN = 3;

    /**
     * 日志：错误模式
     *
     * @var int
     */
    const LEVEL_ERROR = 4;

    /**
     * 日志：疯狂模式
     *
     * @var int
     */
    const LEVEL_CRAZY = 5;

    /**
     * 日志级别名称对照表
     */
    const LEVEL_NAMES = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO ',
        self::LEVEL_WARN => 'WARN ',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_CRAZY => 'CRAZY',
    ];

    /**
     * 日志存放目录
     *
     * @var string
     */
    protected static $dir;

    /**
     * 设置目录
     *
     * @param string $dir
     */
    public static function setDir(string $dir)
    {
        self::$dir = $dir;
    }

    /**
     * 记录日志
     *
     * @param string $message 日志信息
     * @param int $level 日志级别
     */
    public static function record(string $message, int $level = self::LEVEL_DEBUG)
    {
        // 补充时间
        $message = chaserDatetime() . ' | ' . self::getLevelName($level) . ' | ' . $message . PHP_EOL;
        // 记录日志
        file_put_contents(self::$dir . date('Y-m-d') . '.log', $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * 获取日志级别名称
     *
     * @param int $level 级别
     * @return string
     */
    protected static function getLevelName(int $level)
    {
        return self::LEVEL_NAMES[$level] ?? self::LEVEL_NAMES[self::LEVEL_DEBUG];
    }
}
