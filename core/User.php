<?php
/**************************************
 * L5 SNS 用户管理类
 * @version 1.0
 * @author lonphy
 ***************************************/

class User {
	private static $_id = 1;
	public $info = array();		// 用户信息
	public $isLinked = false;	// 是否已连接
	public $isLogin = false;		// 是否已登陆
	public $link;				// 连接socket
	
	function __construct($sock){
		$this->link = $sock;
	}
	/**
	 * 发送消息
	 * @param string $data
	 */
	public function send($data) {
		socket_send($this->link, $data, strlen($data), 0);
	}

	/**
	 * 用户登录
	 * @param string $username 用户名
	 */
	public function logindo($username) {
		$info = array();
		$info['name'] = $username;
		$info['id'] = self::$_id++;
		echo '--------------- user '.$username.' check login------------------'.PHP_EOL;
		$this->info = $info;
		$this->isLogin = true;
		return true;
	}

	/**
	 * 获取用户信息
	 * @return array
	 */
	public function getInfo() {
		return $this->info;
	}
}