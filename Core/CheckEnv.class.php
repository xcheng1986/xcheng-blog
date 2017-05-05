<?php

namespace Core;

use \Index;

/**
 * Description of CheckEnv
 *
 * @author lxc
 */
class CheckEnv {

	private static $fatalErrorList = [];

	public static function check() {
		self::check_phpVersion();
		self::check_unWin();
		self::check_isCli();
		self::check_pcntl();
		self::check_posix();
		self::check_argv();
		self::check_logFile();
		self::check_pidFile();

		if (!empty(self::$fatalErrorList))
			exit(implode("\r\n", self::$fatalErrorList));
	}

	/**
	 * PHP环境必须大于PHP5.3
	 */
	private static function check_phpVersion() {
		if (!substr(PHP_VERSION, 0, 3) >= '5.5') {
			self::$fatalErrorList[] = "Fatal error: Service requires PHP version must be greater than 5.3(contain 5.3). Because Service used php-namespace";
		}
	}

	/**
	 * 不支持在Windows下运行
	 */
	private static function check_unWin() {
		if (strpos(strtolower(PHP_OS), 'win') === 0) {
			self::$fatalErrorList[] = "Fatal error: Service not support Windows. Because the required extension is supported only by Linux, such as php-pcntl, php-posix";
		}
	}

	/**
	 * 必须运行在命令行下
	 */
	private static function check_isCli() {
		if (php_sapi_name() != 'cli') {
			self::$fatalErrorList[] = "Fatal error: Service must run in command line!";
		}
	}

	/**
	 * 是否已经安装PHP-pcntl 扩展
	 */
	private static function check_pcntl() {
		if (!extension_loaded('pcntl')) {
			self::$fatalErrorList[] = "Fatal error: Service must require php-pcntl extension. Because the signal monitor, multi process needs php-pcntl\nPHP manual: http://php.net/manual/zh/intro.pcntl.php";
		}
	}

	/**
	 * 是否已经安装PHP-posix 扩展
	 */
	private static function check_posix() {
		if (!extension_loaded('posix')) {
			self::$fatalErrorList[] = "Fatal error: Service must require php-posix extension. Because send a signal to a process, get the real user ID of the current process needs php-posix\nPHP manual: http://php.net/manual/zh/intro.posix.php";
		}
	}

	/**
	 * 启动参数是否正确
	 * @global type $argv
	 */
	private static function check_argv() {
		global $argv;
		if (!isset($argv[1]) || !in_array($argv[1], array('start', 'stop', 'restart', 'status'))) {
			self::$fatalErrorList[] = "Fatal error: Service needs to receive the execution of the operation.\nUsage: php index.php start|stop|restart|status\n\"";
		}
	}

	/**
	 * 日志检查
	 */
	private static function check_logFile() {
		$log_file = Index::$config['common']['log_file'];
		//日志目录是否存在
		if (!file_exists(dirname($log_file))) {
			if (@!mkdir(dirname($log_file), 0777, true)) {
				self::$fatalErrorList[] = "Fatal error: Log file directory creation failed: ";
			}
		}
		//日志目录是否可写
		if (!is_writable(dirname($log_file))) {
			self::$fatalErrorList[] = "Fatal error: Log file path not to be written: ";
		}
	}

	/**
	 * PID文件检查
	 */
	private static function check_pidFile() {
		$pid_file = Index::$config['common']['master_pid_file'];
		//Pid文件目录是否存在
		if (!file_exists(dirname($pid_file))) {
			if (@!mkdir(dirname($pid_file), 0777, true)) {
				self::$fatalErrorList[] = "Fatal error: master pid file directory creation failed: ";
			}
		}
		//Service主进程Pid文件目录是否可写
		if (!is_writable(dirname($pid_file))) {
			self::$fatalErrorList[] = "Fatal error: master pid file path not to be written: ";
		}
	}

}
