<?php
/**************************************
 * L5 SNS Socket核心基类
 * @version 1.0
 * @author lonphy
 ***************************************/
require_once(APP.'/conf/config.php');
require_once('User.php');
require_once('WsPacket.php');

abstract class Socket{
	private static $MAX_BUFF = 20480;

	private $master;
	
	// 用户连接列表
	protected $sockets = array();
	
	// 用户列表
	protected $users = array();

	/**
	 * 服务端主循环
	 */
	public function run() {
		while(true){
			$changed = $this->sockets;
			socket_select($changed, $write=NULL, $except=NULL, NULL);
			foreach($changed as $socket) {
				if($socket === $this->master) {
					$client = socket_accept($socket);
					$user = new User($client);
					$this->sockets[] = $client;
					$this->users[] = $user;
				}else{
					$bytes = @socket_recv($socket, $buffer, self::$MAX_BUFF, 0);
					if($bytes === 0) {
						$this->_closeSocket($socket);
					}else{
						$userInfo = $this->_getUserBySocket($socket);
						// $userInfo !== null;
						$user = $userInfo[1];
						if(!$user->isLinked){
							$this->_handshake($user, $buffer);
						}else{
							$this->_process($user, $buffer);
						}
					}
				}
			}
		}
	}

	
	/**
	 * 构造函数, 创建一个socket
	 */
	function __construct() {
		global $config;
		$this->master = $this->_createSocket($config['host'], $config['port']);
		$this->sockets[] = $this->master;
	}

	function __destruct() {
		foreach ($this->sockets as $socket){
			@socket_close($socket);
		}
	}

	/**
	 * 关闭指定socket
	 * @param resource Socket $sock
	 */
	private function _closeSocket($sock) {
		$userInfo = $this->_getUserBySocket($sock);
		$this->onDisconnect($userInfo[1]);
		if(!is_null($userInfo)){
			$user = $userInfo[1];
			$frame = new WsCloseFrame();
			$user->send($frame->build());
			unset($this->users[$userInfo[0]]);
		}
		if(($index = array_search($sock, $this->sockets)) > 0){
			@socket_close($sock);
			unset($this->sockets[$index]);
		}
	}
	
	/**
	 * 系统广播
	 * @param array $message
	 * @param string $type 广播类型
	 */
	protected function broadcast($message, $type = 'connected') {
		if(empty($message)) return;
		$users = $this->users;
		$uid = $message['id'];
		$frame = new WsFrame(array('type'=>$type, 'data'=>$message));
		$msg = $frame->build();
		foreach($users as $v){
			if($v->isLogin && $v->info['id'] !== $uid){
				$v->send($msg);
			}
		}
	}
	
	/**
	 * 创建主socket
	 * @param string $address ip地址
	 * @param int $port 端口
	 * @return resource Socket
	 */
	private function _createSocket($address, $port) {
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("create socket error");
		socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) or die("setoption error");
		socket_bind($socket, $address, $port) or die("bind error");
		socket_listen($socket, 5);
		Output::info('Server start, listening '. $address .':'.$port);

		return $socket;
	}

	/**
	 * 通过socket获取对应用户
	 * @param resource Socket $sock
	 * @return array<int, User>
	 */
	private function _getUserBySocket($sock) {
		$found = null;
		foreach($this->users as $k => $user) {
			if($user->link === $sock) {
				$found = array($k, $user);
				break;
			}
		}

		return $found;
	}

	/**
	 * websocket握手
	 * @param User $user 用户
	 * @param string $buffer HTTP头信息
	 * @return boolean
	 */
	private function _handshake(User &$user, $buffer) {
		$header = WsPacket::getHeaders($buffer);
		$response = WsPacket::handshake($header);
		$user->send($response);
		$user->isLinked = true;
	}

	/**
	 * 数据分发
	 * @param User $user
	 * @param string $buffer
	 */
	private function _process(User &$user, $buffer) {
		$frame = new WsFrame($buffer, 'in');
		if($frame->opcode === WsFrame::$OPCODE_CLOSE) {
			$this->_closeSocket($user->link);
		}else{
			$this->onMessage($user, $frame->getData());
		}
		$frame = null;
	}
	
	/**
	 * 接收到消息
	 * @param User $user
	 * @param array $data
	 */
	abstract protected function onMessage(User &$user, $data);

	/**
	 * 用户断开连接
	 * @param User $user
	 */
	abstract protected function onDisconnect(User $user);
}
