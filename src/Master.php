<?php

namespace chaser\server;

/**
 * 管理类
 *
 * @package chaser\server
 */
class Master
{
    /**
     * 配置文件路径
     *
     * @var string
     */
    protected $profile;

    /**
     * 初始化配置
     *
     * @param string $profile
     */
    public function __construct(string $profile = '')
    {
        $this->checkEnv();

        $this->profile = realpath($profile ?: __DIR__ . '/../config.php');
    }

    /**
     * 检查运行环境
     */
    protected function checkEnv()
    {
        PHP_SAPI === 'cli' || $this->quit('only run in command line mode');
        PHP_OS === 'Linux' || $this->quit('only run in Linux platform');
        extension_loaded('posix') || $this->quit('please ensure POSIX extension are installed');
        extension_loaded('pcntl') || $this->quit('please ensure PCNTL extension are installed');
    }

    /**
     * 打印错误并退出程序
     *
     * @param string $message 错误信息
     * @param int $status 错误号
     */
    public function quit(string $message, int $status = 0)
    {
        echo $message, PHP_EOL;
        exit($status);
    }
}
