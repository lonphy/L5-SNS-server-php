<?php
/**
 * L5 SNS Websocket信令简单处理类
 * @author lonphy
 * @version 1.0
 */
class WebSocket extends Socket {
	/**
	 * 业务处理模块
	 * @see Socket::onMessage()
	 * @param User $user
	 * @param array $data
	 */
	public function onMessage(User &$user, $data) {
		switch($data['cmd']) {
			case 'login' :
				if($user->logindo($data['username'])) {

					// 为登录用户发送在线用户列表
					$info = $user->getInfo();
					$sendData = array('me'=>$info, 'list'=>$this->_getOnlines($info['id']));
					$frame = new WsFrame(array('type'=>'login', 'data'=>$sendData));
					$user->send($frame->build());

					// 广播给其他在线用户
					$this->broadcast($info, 'new_login');
				}
				break;
			case 'syscall':
			case 'resyscall':
			case 'media_call':
			case 'candidate':
				$msg = $data['data'];
				$tar = $this->_getUserByid($msg['to']);
				$frame = new WsFrame(array('type'=>$data['cmd'], 'data'=>$msg));
				$tar->send($frame->build());
				break;
		}
	}

	/*******************************
	 * 获取在线用户列表
	 * @param int $id 用户ID
	 * @return array 用户列表
	 *******************************/
	private function _getOnlines($id) {
		$users = $this->users;
		$list = array();
		foreach($users as $v) {
			$info = $v->getInfo();
			if($v->isLogin&& $info['id'] !== $id) {
				$list[] = $info;
			}
		}
		return $list;
	}
	
	/********************************
	 * 获取指定用户
	 * @param int $id 用户ID
	 * @return User user
	 ********************************/
	private function _getUserByid($id) {
		$users = $this->users;
		$user = null;
		foreach($users as $v) {
			$info = $v->getInfo();
			if($v->isLogin&& $info['id'] === $id) {
				$user = $v;
				break;
			}
		}
		return $user;		
	}


	public function onDisconnect(User $user) {
		echo $user->info['name'].' Client is closed!'. PHP_EOL;
		$this->broadcast($user->getInfo(), 'logout');
	}
}