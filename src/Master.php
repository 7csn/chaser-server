<?php

namespace chaser\server;

use chaser\container\Container;
use chaser\server\worker\Http;
use chaser\server\worker\Worker;

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
     * 服务器需求配置
     *
     * @var array
     */
    protected $requirements = [];

    /**
     * 信息存储目录
     *
     * @var string
     */
    protected $storageDir;

    /**
     * 职能列表
     *
     * @var array
     */
    protected $functions = [
        'http' => Http::class
    ];

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
     * 工作实例列表
     *
     * @var array [$hash => Worker]
     */
    protected $workers = [];

    /**
     * 员工工号（进程号）列表
     *
     * @var array [$hash => [$pid => $pid]]
     */
    protected $pidMap = [];

    /**
     * 员工座位（索引）工号对照表
     *
     * @var array [$hash => [$seat => $pid]]
     */
    protected $seatMap = [];

    /**
     * 初始化运行环境
     *
     * @param Container $container
     * @param Log $log
     * @param string $profile
     */
    public function __construct(Container $container, Log $log, string $profile = '')
    {
        $this->checkEnv();

        $this->container = $container;
        $this->log = $log;
        $this->profile = realpath($profile ?: __DIR__ . '/../config.php');

        $this->container->singleton(Master::class, $this);
        $this->container->singleton(Log::class, $log);

        $this->initialize();

        set_error_handler([$this, 'errorHandler']);
    }

    /**
     * 系统运行
     */
    public function run()
    {
        $this->lock();
        $this->parseCommand();
        $this->inDaemonMode();
    }

    /**
     * 检查运行环境
     */
    protected function checkEnv()
    {
        PHP_SAPI === 'cli' || $this->quit('only run in command line mode');
        PHP_OS === 'Linux' || $this->quit('only run in Linux platform');
    }

    /**
     * 加载配置
     */
    protected function initialize()
    {
        $this->settings();

        date_default_timezone_set($this->timezone);

        $this->runtimePath();

        $this->log->setDir($this->logDir);

        $this->setCmdTitle("Chaser：{$this->startFile}");

        $this->initWorkers();
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

    /**
     * 设置进程标题
     *
     * @param string $title
     */
    public function setCmdTitle($title)
    {
        if (function_exists('cli_set_process_title')) {
            set_error_handler(function () {
            });
            cli_set_process_title($title);
            restore_error_handler();
        } elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
            setproctitle($title);
        }
    }

    /**
     * 初始化工作类
     */
    protected function initWorkers()
    {
        array_walk($this->requirements, function ($configurations, $remit) {
            if (key_exists($remit, $this->functions)) {
                $workerClass = $this->functions[$remit];
                array_walk($configurations, function ($configuration, $target) use ($workerClass) {
                    $this->addWorker($this->container->callObject(
                        [$workerClass, 'settings'],
                        compact('configuration'),
                        is_numeric($target) ? [] : compact('target')
                    ));
                });
            }
        });
    }

    /**
     * 添加工作模板
     *
     * @param Worker $worker
     */
    protected function addWorker($worker)
    {
        // 工作标识
        $hash = spl_object_hash($worker);

        if (key_exists($hash, $this->workers)) {
            // 重载
            $worker->reload();
            // 扩充座位（如不够）
            $this->seatMap[$hash] = array_pad($this->seatMap[$hash], count($worker), 0);
        } else {
            // 初始化
            $worker->initialize($this);
            // 记录新职位、初始化工号列表
            $this->workers[$hash] = $worker;
            $this->pidMap[$hash] = [];
            // 初始化座位
            $this->seatMap[$hash] = array_fill(0, count($worker), 0);
        }
    }

    /**
     * 错误处理
     *
     * @param int $code
     * @param string $message
     * @param string $file
     * @param int $line
     */
    public function errorHandler($code, $message, $file, $line)
    {
        $type = 'Other Error';
        foreach ([
                     'Fatal Error' => [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
                     'Parse Error' => [E_PARSE],
                     'Warning' => [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING],
                     'Notice' => [E_NOTICE, E_USER_NOTICE],
                 ] as $errorType => $errorCode) {
            if (in_array($code, $errorCode)) {
                $type = $errorType;
                break;
            }
        }
        $errorInfo = $type . '：[ ' . $message . ' ][ ' . $file . ' ][ ' . $line . ' ]';
        $this->log->record($errorInfo, Log::LEVEL_ERROR);
    }

    /**
     * 锁定启动文件
     */
    protected function lock()
    {
        $fd = fopen($this->startFile, 'r');
        if (!$fd || !flock($fd, LOCK_EX)) {
            $this->quit('Master already running');
        }
    }

    /**
     * 解析命令
     */
    protected function parseCommand()
    {
        $argc = $_SERVER['argc'];
        $argv = $_SERVER['argv'];

        // 命令、模式
        $command = $argc < 2 ? 'start' : $argv[1];
        $mode = $argc > 2 ? $argv[2] : null;

        // 其它运行中的主进程 PID
        $otherPid = $this->getOtherPid();
        $runningPid = $otherPid > 0 && posix_kill($otherPid, 0) ? $otherPid : 0;

        switch ($command) {
            case 'start':
                $runningPid > 0 && $this->quit('Master already running');
                break;
            case 'restart':
                $stopGraceful = $mode === '-g';
                $this->stopRunningMaster($runningPid, $stopGraceful);
                break;
            case 'stop':
                $stopGraceful = $mode === '-g';
                $this->stopRunningMaster($runningPid, $stopGraceful);
                exit(0);
            case 'reload':
                break;
            case 'status':
                break;
            case 'connections':
                break;
            default:
                $this->quit("Unknown command:$command");
        }
    }

    /**
     * 获取其它可能主进程 PID
     *
     * @return int
     */
    protected function getOtherPid()
    {
        $pid = is_file($this->pidFile) ? (int)file_get_contents($this->pidFile) : 0;
        return posix_getpid() === $pid ? 0 : $pid;
    }

    /**
     * 指令：停止正运行的系统
     *
     * @param int $pid 活跃系统主进程号
     * @param bool $graceful 是否优雅关闭
     */
    protected function stopRunningMaster($pid, $graceful)
    {
        $pid > 0 || $this->quit('Master not run');

        if ($graceful) {
            $this->log->record('Master is gracefully stopping ...');
        } else {
            $this->log->record('Master is stopping ...');
        }
    }

    /**
     * 以守护进程方式运行
     */
    protected function inDaemonMode()
    {
        // 设置临时最大文件权限
        umask(0);

        // 分叉并退出父进程，让shell认为命令终止不用挂在终端上，摆脱进程组长身份
        $pid = pcntl_fork();
        $pid === -1 && $this->quit('daemon mode: fork fail');
        $pid > 0 && exit(0);

        // 创建（不能是组长）没有控制终端的新会话，并成为组长
        posix_setsid() === -1 && $this->quit('daemon mode: setSid fail');

        // 再次分叉摆脱组长身份（组长可能会打开控制终端）
        $pid = pcntl_fork();
        $pid === -1 && $this->quit('daemon mode: second fork fail');
        $pid > 0 && exit(0);
    }
}
