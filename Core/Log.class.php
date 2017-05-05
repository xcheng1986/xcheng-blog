<?php

namespace Core;

/**
 * Description of Log
 *
 * @author lxc
 */
class Log {

	private static $fileResource = null;

	/**
	 * 单例方法,用于访问实例的公共的静态方法
	 * @return type
	 */
	public static function getInstance() {
		if (is_null(self::$fileResource)) {
			self::$fileResource = fopen(\Index::$config['common']['log_file'], 'a');
		}
	}

	/**
	 * write
	 * @param type $msg
	 */
	public static function write($msg, $type = 'INFO') {
		self::getInstance();
		if (!$type)
			$type = 'INFO';
		$massage = '[' . date('Y-m-d H:i:s') . '][' . $type . ']' . $msg . "\n";
		if (\Index::$isDaemon == false)
			echo $massage;
		else
			fwrite(self::$fileResource, $massage);
	}

}
