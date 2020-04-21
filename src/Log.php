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
    protected $dir;

    /**
     * 设置目录
     *
     * @param string $dir
     * @return $this
     */
    public function setDir(string $dir)
    {
        $this->dir = $dir;

        return $this;
    }

    /**
     * 记录日志
     *
     * @param string $message 日志信息
     * @param int $level 日志级别
     */
    public function record(string $message, int $level = self::LEVEL_DEBUG)
    {
        // 补充时间
        $message = $this->getDatetime() . ' | ' . $this->getLevelName($level) . ' | ' . $message . PHP_EOL;
        // 记录日志
        file_put_contents($this->dir . date('Y-m-d') . '.log', $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * 获取当前精确时间
     *
     * @return string
     */
    protected function getDatetime()
    {
        return date('H:i:s') . '.' . str_pad(substr(microtime(true), 11), 6, '0');
    }

    /**
     * 获取日志级别名称
     *
     * @param int $level 级别
     * @return string
     */
    protected function getLevelName(int $level)
    {
        return self::LEVEL_NAMES[$level] ?? self::LEVEL_NAMES[self::LEVEL_DEBUG];
    }
}
