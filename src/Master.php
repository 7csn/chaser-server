<?php

namespace chaser\server;

use chaser\container\Container;

/**
 * 管理类
 *
 * @package chaser\server
 */
class Master
{
    /**
     * IoC 容器
     *
     * @var Container
     */
    protected $container;

    /**
     * 配置文件路径
     *
     * @var string
     */
    protected $profile;

    /**
     * 时区
     *
     * @var string
     */
    protected $timezone = 'UTC';

    /**
     * 信息存储目录
     *
     * @var string
     */
    protected $storageDir;

    /**
     * 启动文件
     *
     * @var string
     */
    protected $startFile;

    /**
     * 主进程 PID 存储文件
     *
     * @var string
     */
    protected $pidFile;

    /**
     * 日志目录
     *
     * @var string
     */
    protected $logDir;

    /**
     * 日志对象
     *
     * @var Log
     */
    protected $log;

    /**
     * 初始化运行环境
     *
     * @param string $profile
     */
    public function __construct(Container $container, string $profile = '')
    {
        $this->checkEnv();

        $this->container = $container;

        $this->container->single('log');

        $this->profile = realpath($profile ?: __DIR__ . '/../config.php');

        $this->initialize();
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
     * 加载配置
     */
    protected function initialize()
    {
        $this->settings();

        date_default_timezone_set($this->timezone);

        $this->runtimePath();

        $this->log = $this->container->make(Log::class)->setDir($this->logDir);
    }

    /**
     * 重置属性配置
     */
    protected function settings()
    {
        foreach (include $this->profile as $name => $value) {
            $this->{$name} = $value;
        }
    }

    /**
     * 初始化运行目录文件路径
     */
    protected function runtimePath()
    {
        // 初始化存储目录
        $this->storageDir = realpath($this->storageDir ?: __DIR__ . '/../storage') . DIRECTORY_SEPARATOR;
        is_dir($this->storageDir) || mkdir($this->storageDir, 0777);

        // 获取启动文件路径
        $backtrace = debug_backtrace();
        $this->startFile = end($backtrace)['file'];

        // 启动文件路径标记
        $name = str_replace(['/', '\\', '.'], '_', $this->startFile);

        // 初始化日志目录
        $this->logDir = $this->storageDir . $name . DIRECTORY_SEPARATOR;
        is_dir($this->logDir) || mkdir($this->logDir, 0777);

        // 主进程 PID 保存文件
        $this->pidFile = $this->storageDir . $name . '.pid';
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
