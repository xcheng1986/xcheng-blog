<?php

use Core\HttpServer;
use Core\Log;

class Index {

	public static $config = [];
	public static $class_obj = [];
	public static $isDaemon = false;

	public static function run() {

		//初始化
		self::_init();

		//命令解析
		self::_parseCommand();
	}

	/**
	 * init
	 */
	public static function _init() {

		//自动加载
		spl_autoload_register(function($name) {
			$class_path = __DIR__ . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.class.php';
			if (!is_file($class_path))
				die('文件' . $class_path . '未找到');
			if (!isset(self::$class_obj[$class_path]))
				include $class_path;
		});

		//全局配置加载
		$user_config_file = __DIR__ . '/conf.ini';
		self::$config = parse_ini_file($user_config_file, true) ?: [];

		//运行环境检查
		\Core\CheckEnv::check();

		//异常退出检查
		register_shutdown_function(function() {
			$errno = error_get_last();
			if (!is_null($errno)) {
				Log::write("Service normal exit. \$errno:$errno");
				return true;
			}
		});
	}

	/**
	 * 解析启动命令,比如start, stop等 执行不同的操作
	 */
	private static function _parseCommand() {
		global $argv;
		$startFilename = trim($argv[0]);
		$operation = strtolower(trim($argv[1]));
		$isDomain = isset($argv[2]) && trim($argv[2]) === '-d' ? true : false;
		//获取主进程ID - 用来判断当前进程是否在运行
		$masterPid = false;
		if (file_exists(self::$config['common']['master_pid_file']))
			$masterPid = file_get_contents(self::$config['common']['master_pid_file']);

		//主进程当前是否正在运行
		$masterIsAlive = false;
		//给Service主进程发送一个信号, 信号为SIG_DFL, 表示采用默认信号处理程序.如果发送信号成功则该进程正常
		if ($masterPid && self::checkMasterIsAlive())
			$masterIsAlive = true;

		//不能重复启动
		if ($masterIsAlive && $operation === 'start')
			Log::write('Service is already running. file: ' . $startFilename, 'FATAL');

		//未启动不能查看状态
		if (!$masterIsAlive && $operation === 'status')
			Log::write('Service is not running.', 'FATAL');

		//未启动不能终止
		if (!$masterIsAlive && $operation === 'stop')
			Log::write('Service is not running.', 'FATAL');

		//根据不同的执行参数执行不同的动作
		switch ($operation) {
			//启动
			case 'start':
				self::_commandStart($isDomain);
				break;
			//停止
			case 'stop':
				self::_commandStop($masterPid);
				break;
			//重启
			case 'restart':
				self::_commandRestart($masterPid, $isDomain);
				break;
			//状态
			case 'status':
				self::_commandStatus($masterPid);
				break;
			//参数不合法
			default:
				Log::write('Parameter error. Usage: php index.php start|stop|restart|status', 'FATAL');
				echo "Usage: php " . basename(__FILE__) . " start|stop|restart|status [-d]\n";
				exit(1);
		}
	}

	/**
	 * 启动
	 * @param type $isDomain
	 */
	private static function _commandStart($isDomain) {
		if ($isDomain) {
			self::$isDaemon = true;
			self::_daemon();
		}

		//启动HttpServer
		self::_startHttp();
	}

	/**
	 * 停止
	 * @param type $masterPid
	 */
	private static function _commandStop($masterPid) {
		Log::write('Service receives the "stop" instruction, Service will graceful stop.');

		exec("ps aux | grep $masterPid | grep -v grep | awk '{print $2}' |xargs kill -SIGINT");
		exit(0);
	}

	/**
	 * 重启
	 * @param type $masterPid
	 * @param type $isDomain
	 */
	private static function _commandRestart($masterPid, $isDomain) {
		Log::write('receives the "restart" instruction, Service will graceful restart.');

		exec("ps aux | grep $masterPid | grep -v grep | awk '{print $2}' |xargs kill -SIGINT");

		if ($isDomain === true)
			self::_commandStart($isDomain);

		//启动HttpServer
		self::_startHttp();
	}

	/**
	 * 状态
	 * @param type $masterPid
	 */
	private static function _commandStatus($masterPid) {
		$isAlive = self::checkMasterIsAlive();

		if (!$isAlive)
			echo "service is not running.\n";
		else
			echo "service is running. Pid:$masterPid\n";
		exit(0);
	}

	/**
	 * 已守护进程的方式启动
	 */
	private static function _daemon() {
		//文件掩码清0
		umask(0);
		//创建一个子进程
		$pid = pcntl_fork();
		//fork失败
		if ($pid === -1) {
			Log::write('_daemon: fork failed', 'FATAL');
			//父进程
		} else if ($pid > 0) {
			exit();
		}
		//设置子进程为Session leader, 可以脱离终端工作.这是实现daemon的基础
		if (posix_setsid() === -1) {
			Log::write('_daemon: set sid failed', 'FATAL');
		}
		//再次在开启一个子进程
		//这不是必须的,但通常都这么做,防止获得控制终端.
		$pid2 = pcntl_fork();
		if ($pid2 === -1) {
			Log::write('_daemon: fork2 failed', 'FATAL');
			//将父进程退出
		} else if ($pid !== 0) {
			exit();
		}
	}

	/**
	 *
	 */
	private static function _startHttp() {
		//保存pid
		self::_saveMasterPid();
		//设置进程名称
		self::setProcessTitle(self::$config['common']['master_process_name']);
		//启动服务
		HttpServer::run('tcp://0.0.0.0:8081');
	}

	private static function checkMasterIsAlive() {
		$process_name = self::$config['common']['master_process_name'];
		exec("ps -ef | grep $process_name | grep -v grep", $output);
		if (empty($output))
			return false;
		else
			return true;
	}

	/**
	 * 保存主进程的Pid
	 */
	private static function _saveMasterPid() {
		if (false === file_put_contents(self::$config['common']['master_pid_file'], posix_getpid())) {
			Log::write('Can\'t write pid to ' . self::$config['common']['master_pid_file'], 'FATAL');
		}
	}

	/**
	 * 设置进程名称
	 * @param type $title
	 */
	public static function setProcessTitle($title) {
		if (function_exists('cli_set_process_title')) {
			cli_set_process_title($title);
		} elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
			setproctitle($title);
		}
	}

}

Index::run();
