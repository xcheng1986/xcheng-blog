<?php

namespace Core;

/**
 * Description of Reponse
 *
 * @author lxc
 */
class Reponse {

	public $head = '';
	public $body = '';

	public function setHttpCode($code = 200, $descript = 'OK') {
		$this->head .= "HTTP/1.1 ${code} ${descript}";
	}

	public function setCookie($name, $value, $expire = 0, $path = '', $domain = '', $secure = false, $httponly = false) {

	}

	public function setContentType($type = 'text/html') {

	}

	public function addHead() {

	}

	public function setBody() {

	}

	public function buildHead() {
		
	}

	public function buildReponse() {

	}

}
