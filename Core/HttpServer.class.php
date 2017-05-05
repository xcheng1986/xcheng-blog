<?php

namespace Core;

use Core\Log;

class HttpServer {

	public static function run($libserver = 'tcp://0.0.0.0:2000') {
		$socket = stream_socket_server($libserver, $errno, $errmsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
		if (!$socket) {
			Log::write('stream_socket_server() error: errno=' . $errno . ' errmsg=' . $errmsg, 'FATAL');
			exit(1);
		}
		stream_set_blocking($socket, 0);
		$base = event_base_new();
		$event = event_new();
		event_set($event, $socket, EV_READ | EV_PERSIST, 'self::http_accept', $base);
		event_base_set($event, $base);
		event_add($event);

		event_base_loop($base);
	}

	private static function http_accept($socket, $flag, $base) {
		static $fid = 0;

		$connection = stream_socket_accept($socket, 0, $peerName);
		stream_set_timeout($connection, 2);
		stream_set_blocking($connection, 0);

		$accept_ip = stream_socket_get_name($connection, true);
		Log::write("新的连接建立\$fid:$fid \naccept_ip:$accept_ip\n");

		//注册缓冲区读
		$buffer = event_buffer_new($connection, 'self::http_read', 'self::http_write', 'self::http_error', array($base, $fid));
		if ($buffer == false)
			die('error to new buffered event.');
		event_buffer_base_set($buffer, $base);
		event_buffer_timeout_set($buffer, $read_timeout = 30, $write_timeout = 30);
		event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
		event_buffer_priority_set($buffer, 10);
		event_buffer_enable($buffer, EV_READ | EV_PERSIST);

		//保存
		$GLOBALS['hq_connections'][$fid] = $connection;
		$GLOBALS['hq_buffers'][$fid] = $buffer;

		$fid++;
	}

	/**
	 * http_read
	 * @param type $buffer
	 * @param type $arr
	 */
	private static function http_read($buffer, $arr) {
		$fid = $arr[1];
		$request_string = '';
		while ($read = event_buffer_read($buffer, 1024))
			$request_string .= $read;
		Log::write("收到连接${fid}的请求\n\$fid:{$fid}\n------------------", 'INFO');

		$cookie_expires = gmstrftime("%A, %d-%b-%Y %H:%M:%S GMT", time() + 9600);
		$html = <<<EOF
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
	</head>
	<body>
		<h3>libevent-test</h3>
		<p>\$fid:$fid</p>
		<P>Request:<br/><pre>$request_string</pre></p>
	</body>
</html>
EOF;
		$len = strlen($html);
		$string = <<<EOF
HTTP/1.1 200 OK
Server: FK-Blog-1.0
Content-Type: text/html
Content-Length: $len
Connection: keep-alive
Set-Cookie: testCookie=g4jdg1gqgoohg18apkj5cbgg0smb9s0h; path=/; expires={$cookie_expires}

$html
EOF;
		//Http返回数据给用户
		stream_socket_sendto($GLOBALS['hq_connections'][$fid], $string);
	}

	/**
	 * http_write
	 * @param type $buffer
	 * @param type $error
	 * @param type $arr
	 */
	private function http_write($buffer, $arr) {
		$fid = $arr[1];
		Log::write("http_write\n\$fid:$fid\n", 'INFO');
	}

	/**
	 * http_error
	 */
	private function http_error($buffer, $error, $arr) {
		$fid = $arr[1];

		//关闭用户连接
		event_buffer_disable($buffer, EV_READ | EV_WRITE);
		event_buffer_free($buffer);
		fclose($GLOBALS['hq_connections'][$fid]);

		//释放资源
		unset($buffer);
		unset($GLOBALS['hq_connections'][$fid]);

		Log::write("http_error\n\$fid:$fid\n", 'INFO');
	}

}
