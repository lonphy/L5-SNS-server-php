<?php
/**************************************
 * L5 SNS 命令行调试输出
 * @version 1.0
 * @author lonphy
 ***************************************/

class Output {
	private static $isDebug = false;
	
	/**
	 * 设置调试状态
	 * @param string $state on or off
	 */
	public static function setDebug($state = 'on') {
		if($state === 'off') {
			self::$isDebug = false;
		} else {
			self::$isDebug = true;
		}
	}
	
	/**
	 * 输出消息
	 * @param unknown $msg
	 */
	public static function info($msg) {
		$prefix = date('Y-m-d H:i:s');
		switch (gettype($msg)){
			case 'array':
			case 'object':
				print_r($msg);
				break;
			default:
				echo $prefix. ' : ' .$msg . PHP_EOL;
		}
	}

	/**
	 * 输出调试信息
	 * @param string $msg
	 */
	public static function log($msg) {
		if(self::$isDebug) {
			self::log($msg);
		}
	}
}