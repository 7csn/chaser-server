<?php

if (!function_exists('chaserExit')) {
    /**
     * 打印信息并退出程序
     *
     * @param string $message
     * @param int $status
     */
    function chaserExit(string $message, int $status = 0)
    {
        echo $message, PHP_EOL;
        exit($status);
    }
}

if (!function_exists('chaserSetCmdTitle')) {
    /**
     * 设置进程标题
     *
     * @param string $title
     */
    function chaserSetCmdTitle(string $title)
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
}

if (!function_exists('chaserPortReused')) {
    /**
     * 判断系统是否支持端口复用
     *
     * @return bool|null
     */
    function chaserPortReused()
    {
        preg_match('/version ([\d\.]+)/', exec("cat /proc/version"), $match);
        return isset($match[1])
            ? version_compare($match[1], 3.9) ? true : false
            : null;
    }
}

if (!function_exists('chaserIgnoreErrorCall')) {
    /**
     * （忽略错误）执行调用
     *
     * @param callable $name
     * @param array ...$args
     * @return mixed
     */
    function chaserIgnoreErrorCall($name, ...$args)
    {
        set_error_handler(function () {
        });
        $result = call_user_func_array($name, $args);
        restore_error_handler();
        return $result;
    }
}

if (!function_exists('chaserDatetime')) {
    /**
     * 获取当前精确日期时间
     *
     * @param int $decimals
     * @return string
     */
    function chaserDatetime($decimals = 4)
    {
        return date('H:i:s') . '.' . str_pad(substr(microtime(true), 11), $decimals, '0');
    }
}

if (!function_exists('chaserFullyQualifiedNames')) {
    /**
     * 获取指定目录下全限定类名列表
     *
     * @param string $namespace
     * @param string $path
     * @param string[] $classes [...$class]
     * @return array
     */
    function chaserFullyQualifiedNames($namespace, $path, $classes = [])
    {
        $len = strlen($path);
        foreach (glob($path . '*.php') as $file) {
            $classes[] = $namespace . str_replace(DIRECTORY_SEPARATOR, '\\', substr($file, $len, -4));
        }
        foreach (glob($path . '*', GLOB_ONLYDIR) as $path) {
            $classes = chaserFullyQualifiedNames(
                $namespace . str_replace(DIRECTORY_SEPARATOR, '\\', substr($path, $len)) . '\\',
                $path . DIRECTORY_SEPARATOR,
                $classes
            );
        }
        return $classes;
    }
}
