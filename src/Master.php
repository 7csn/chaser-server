<?php

namespace chaser\server;

use chaser\container\Container;
use chaser\server\reactor\Reactor;
use chaser\server\reactor\Select;
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
     * 强关系统时限（秒）
     */
    const STOP_FORCEFUL_TIMEOUT = 5;

    /**
     * 状态：准备中
     *
     * @var int
     */
    const STATUS_STARTING = 1;

    /**
     * 状态：运行中
     *
     * @var int
     */
    const STATUS_RUNNING = 2;

    /**
     * 状态：即将终止
     *
     * @var int
     */
    const STATUS_SHUTDOWN = 3;

    /**
     * 状态：正在重载
     *
     * @var int
     */
    const STATUS_RELOADING = 4;

    /**
     * 当前进程状态
     *
     * @var int
     */
    protected $status = self::STATUS_STARTING;

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
     * 启动文件名
     *
     * @var string
     */
    protected $startFile;

    /**
     * 启动文件别名
     *
     * @var string
     */
    protected $startName;

    /**
     * 主进程 PID 存储文件
     *
     * @var string
     */
    protected $pidFile;

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
     * @param string $profile
     */
    public function __construct(Container $container, string $profile = '')
    {
        $this->checkEnv();

        $this->container = $container;
        $this->profile = realpath($profile ?: __DIR__ . '/../config.php');

        $this->container->single(Reactor::class);
        $this->container->concretes(Select::class, Reactor::class);

        $this->startPath();

        $this->parseCommand();

        $this->initialize();

        set_error_handler([$this, 'errorHandler']);
    }

    /**
     * 系统运行
     */
    public function run()
    {
        $this->lock();
        $this->inDaemonMode();
        $this->installSignal();
        $this->savePid();
        $this->unlock();
        $this->forkWorkers();
        $this->monitorWorkers();
    }

    /**
     * 检查运行环境
     */
    protected function checkEnv()
    {
        PHP_SAPI === 'cli' || chaserExit('only run in command line mode');
        PHP_OS === 'Linux' || chaserExit('only run in Linux platform');
    }

    /**
     * 启动文件别名路径
     */
    protected function startPath()
    {
        // 获取启动文件路径
        $backtrace = debug_backtrace();
        $this->startFile = end($backtrace)['file'];

        // 启动文件路径标记
        $this->startName = str_replace(['/', '\\', '.'], '_', $this->startFile);

        // 主进程 PID 保存文件
        $this->pidFile = realpath(__DIR__ . '/../storage') . DIRECTORY_SEPARATOR . $this->startName . '.pid';
    }

    /**
     * 加载配置
     */
    protected function initialize()
    {
        $this->settings();

        date_default_timezone_set($this->timezone);

        $this->logPath();

        chaserSetCmdTitle("Chaser：{$this->startFile}");

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
     * 初始化日志目录
     */
    protected function logPath()
    {
        // 初始化存储目录
        $this->storageDir = realpath($this->storageDir ?: __DIR__ . '/../storage') . DIRECTORY_SEPARATOR;

        // 初始化日志目录
        $logDir = $this->storageDir . $this->startName . DIRECTORY_SEPARATOR;
        is_dir($logDir) || mkdir($logDir, 0777);
        Log::setDir($logDir);
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
                    if (!is_numeric($target)) {
                        $configuration['target'] = $target;
                    }
                    $this->addWorker($this->container->make($workerClass, $configuration));
                });
            }
        });
    }

    /**
     * 添加工作模板
     *
     * @param Worker $worker
     */
    public function addWorker(Worker $worker)
    {
        // 工作标识
        $hash = spl_object_hash($worker);

        // 记录新职位、初始化工号列表
        $this->workers[$hash] = $worker;
        $this->pidMap[$hash] = [];

        // 初始化座位
        $this->seatMap[$hash] = array_fill(0, count($worker), 0);
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
        Log::record($errorInfo, Log::LEVEL_ERROR);
    }

    /**
     * 锁定启动文件
     */
    protected function lock()
    {
        $fd = fopen($this->startFile, 'r');
        if (!$fd || !flock($fd, LOCK_EX)) {
            chaserExit('Master already running');
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
                $runningPid > 0 && chaserExit('Master already running');
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
                $runningPid > 0 || chaserExit('Master not run');
                $sig = $mode === '-g' ? SIGQUIT : SIGUSR1;
                posix_kill($runningPid, $sig);
                exit(0);
            case 'status':
                $runningPid > 0 || chaserExit('Master not run');
                break;
            case 'connections':
                $runningPid > 0 || chaserExit('Master not run');
                break;
            default:
                chaserExit("Unknown command:$command");
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
        $pid > 0 || chaserExit('Master not run');

        $this->logPath();

        if ($graceful) {
            $sig = SIGTERM;
            Log::record('Master is gracefully stopping ...');
        } else {
            $sig = SIGINT;
            Log::record('Master is stopping ...');
        }

        // 给运行中的系统发送终止信号
        posix_kill($pid, $sig);

        // 询问系统关闭情况时限
        $limitTime = time() + self::STOP_FORCEFUL_TIMEOUT;

        // 强行关闭，不断询问结果
        if (!$graceful) {
            while (posix_kill($pid, 0)) {
                // 超时，视为关闭失败
                if (time() >= $limitTime) {
                    Log::record('Master stop fail');
                    exit;
                }
                // 等待 10 毫秒
                usleep(10000);
            }
        }

        // 发送信号失败，视为关闭成功
        Log::record('Master stop success');
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
        $pid === -1 && chaserExit('daemon mode: fork fail');
        $pid > 0 && exit(0);

        // 创建（不能是组长）没有控制终端的新会话，并成为组长
        posix_setsid() === -1 && chaserExit('daemon mode: setSid fail');

        // 再次分叉摆脱组长身份（组长可能会打开控制终端）
        $pid = pcntl_fork();
        $pid === -1 && chaserExit('daemon mode: second fork fail');
        $pid > 0 && exit(0);
    }

    /**
     * 安装信号处理器
     */
    protected function installSignal()
    {
        // 终止、优雅终止、重载、优雅重载、状态、连接状态
        $signals = [SIGINT, SIGTERM, SIGUSR1, SIGQUIT, SIGUSR2, SIGIO];
        foreach ($signals as $signal) {
            pcntl_signal($signal, [$this, 'signalHandler'], false);
        }
        // 忽略 SIG_IGN 信号，防止发送数据到已断开 socket 引起的默认进程终止
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    /**
     * 信号处理程序
     *
     * @param int $signal
     */
    public function signalHandler($signal)
    {
        switch ($signal) {
            case SIGINT:    // 停止
                $this->stop(false);
                break;
            case SIGTERM:   // 优雅停止
                $this->stop(true);
                break;
            case SIGQUIT:   // 重载
                $this->reload(false);
                break;
            case SIGUSR1:   // 优雅重载
                $this->reload(true);
                break;
            case SIGUSR2:   // 状态
                break;
            case SIGIO:     // 连接状态
                break;
        }
    }

    /**
     * 终止系统运行
     *
     * @param bool $graceful
     */
    protected function stop(bool $graceful)
    {
        $this->status = static::STATUS_SHUTDOWN;

        Log::record('Master stopping ...');

        $sig = $graceful ? SIGTERM : SIGINT;

        array_walk($this->pidMap, function ($pidMap) use ($sig) {
            array_walk($pidMap, function ($pid) use ($sig) {
                posix_kill($pid, $sig);
                // 询问结果
            });
        });
    }

    /**
     * 系统重载
     *
     * @param bool $graceful 是否优雅关闭
     */
    protected function reload(bool $graceful)
    {
        if ($this->status === self::STATUS_RUNNING) {

            $this->status = self::STATUS_RELOADING;

            Log::record('Master reloading...');

            $this->initialize();

            // 系统重载事件

            $sig = $graceful ? SIGQUIT : SIGUSR1;

            foreach ($this->pidMap as $pidMap) {
                foreach ($pidMap as $pid) {
                    posix_kill($pid, $sig);
                }
            }

            $this->status = self::STATUS_RUNNING;

            Log::record('Master has been reloaded');
        }
    }

    /**
     * 保存 PID
     */
    protected function savePid()
    {
        file_put_contents($this->pidFile, posix_getpid()) || chaserExit("Can not save pid to $this->pidFile");
    }

    /**
     * 启动文件解锁
     */
    protected function unlock()
    {
        $fd = fopen($this->startFile, 'r');
        $fd && flock($fd, LOCK_UN);
    }

    /**
     * 批量招工
     */
    protected function forkWorkers()
    {
        array_walk($this->workers, function ($worker, $hash) {
            $count = count($worker);
            while (count($this->pidMap[$hash]) < $count) {
                $this->forkWorker($hash, $worker);
            }
        });
    }

    /**
     * 招工
     *
     * @param string $hash
     * @param Worker $worker
     */
    protected function forkWorker($hash, $worker)
    {
        // 找出空缺位置
        $seat = $this->getSeat($hash);
        if ($seat === false) {
            return;
        }

        // 招工（分叉子进程）
        $pid = pcntl_fork();
        if ($pid > 0) {
            $this->pidMap[$hash][$pid] = $pid;
            $this->seatMap[$hash][$seat] = $pid;
        } elseif (0 === $pid) {
            $this->status = static::STATUS_RUNNING;
            $this->pidMap = [];
            $this->seatMap = [];
            foreach ($this->workers as $_hash => $_worker) {
                $_hash === $hash || $_worker->__destruct();
            }
            $this->workers = [];
            // 员工工作
            $worker->run();
            die;
        } else {
            exit('forkOneWorker fail');
        }
    }

    /**
     * 获取员工座位
     *
     * @param int $hash 岗位哈希
     * @param int $pid 员工工号
     * @return false|int|string 座号
     */
    protected function getSeat($hash, $pid = 0)
    {
        return array_search($pid, $this->seatMap[$hash]);
    }

    /**
     * 监工：维持系统运行
     */
    protected function monitorWorkers()
    {
        $this->status = static::STATUS_RUNNING;

        while (1) {
            // 调用信号处理程序
            pcntl_signal_dispatch();

            // 挂起，直到子进程退出且其状态未报告，或接收到信号
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);

            // 调用信号处理程序
            pcntl_signal_dispatch();

            // 若有子进程退出（员工离职）
            if ($pid > 0) {
                foreach ($this->pidMap as $hash => $pidMap) {
                    // 找出员工
                    if (key_exists($pid, $pidMap)) {
                        // 异常退出日志
                        if ($status !== 0) {
                            Log::record("worker [{$this->workers[$hash]->name}:$pid] exit with status $status");
                        }
                        // 清除员工工号、置空工位
                        unset($this->pidMap[$hash][$pid]);
                        $this->seatMap[$hash][$this->getSeat($hash, $pid)] = 0;
                        break;
                    }
                }

                // 若主程序依然运行，分叉新的子进程
                if ($this->status !== static::STATUS_SHUTDOWN) {
                    $this->forkWorkers();
                }
            }

            // 若状态为即将终止，则清退程序
            if ($this->status === static::STATUS_SHUTDOWN && !$this->getPidList()) {
                $this->quitCleanly();
            }
        }
    }

    /**
     * 退出当前进程
     */
    protected function quitCleanly()
    {
        @unlink($this->pidFile);
        Log::record('Master has been stopped');
        exit(0);
    }

    /**
     * 返回员工工号列表
     *
     * @return array [pid => pid]
     */
    protected function getPidList()
    {
        return array_reduce($this->pidMap, function ($pidMap, $pidList) {
            return array_merge($pidMap, $pidList);
        }, []);
    }
}
