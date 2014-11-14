<?php
/**
 * L5 SNS Websocket数据包处理类
 * @author lonphy
 * @version 1.0
 */
class WsPacket {
	private static $MAGIC_KEY = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
	
	/**
	 * 握手信息组装
	 * @param array $header
	 * @return string;
	 */
	public static function handshake(array $header) {
		$seckey = $header['Sec-WebSocket-Key'];
		$hashkey = base64_encode(sha1( $seckey . self::$MAGIC_KEY, true));

		$upgrade  = "HTTP/1.1 101 Switching Protocols\r\n" .
				"Upgrade: websocket\r\n" .
				"Connection: Upgrade\r\n" .
				"Sec-WebSocket-Version: 13\r\n".
				"Sec-WebSocket-Accept: " . $hashkey . "\r\n\r\n";

		return $upgrade;
	}

	/**
	 * 解析HTTP头部信息
	 * @param string $buffer
	 * @return array
	 */
	public static function getHeaders($buffer) {
		$buffer = explode("\r\n", $buffer);
		$headers = array();
		$headers['protocol'] = $buffer[1];
		$buffer = array_slice($buffer, 1);
		foreach($buffer as $v){
			$item = explode(': ', $v);
			if($item[0]&&$item[1]){
				$headers[$item[0]] = $item[1];
			}
		}
		return $headers;
	}
}

/**
 * websocket 数据帧
 */
class WsFrame {
	public static $OPCODE_CONTINUATION	= 0x0;
	public static $OPCODE_TEXT			= 0x1;
	public static $OPCODE_BINARY		= 0x2;
	public static $OPCODE_CLOSE			= 0x8;
	public static $OPCODE_PING			= 0x9;
	public static $OPCODE_PONG			= 0xa;

	public $opcode = 0x1;
	public $fin = 1;
	public $dataLen = 0;
	private $type = 'out';

	private $data = '';
	
	public function __construct($param, $type='out'){
		if($type === 'out') {
			$this->setData($param);
		}elseif($type === 'in'){
			$this->type = 'in';
			$this->parse($param);
		}
	}
	
	/**
	 * 打包websocket 数据帧
	 * 只有流出数据包才可以打包
	 * @return string
	 */
	public function build() {
		if($this->type === 'in') return '';

		$buffer = array();
		$pos = 2;
		$buffer[0] = ($this->fin === 1? '8':'0') . dechex($this->opcode);// fin, opcode
		if($this->opcode === self::$OPCODE_TEXT || $this->opcode === self::$OPCODE_BINARY){
			$len = strlen($this->data);
			$data = $this->data;
			
			if($len < 0x7e){ //7bit
				$buffer[1] = self::hexfix($len);
			}elseif($len <= 0xffff){// 16bit
				$buffer[1] = '7e';
				$buffer[2] = self::hexfix($len>>8);
				$buffer[3] = self::hexfix($len&0xff);
				$pos = 4;
			}else{
				return false;
			}
		
			$str = '';
			for($i=0;$i<$len;++$i){
				$str.= dechex(ord($data{$i}));
			}
			$buffer[$pos] = $str;
			
		}else{
			$buffer[1] = '00';
		}
		return pack('H*', implode('', $buffer));
	}	
	
	/**
	 * 将10进制转成2位16进制
	 * @param int $hex
	 * @return string
	 */
	private function hexfix($hex) {
		return ($hex < 16 ? '0' : '').dechex($hex);
	}

	/**
	 * 获取数据帧数据
	 * @return mixed
	 */
	public function getData() {
		return json_decode($this->data, true);
	}

	/**
	 * 放入数据帧数据
	 * 只有流出数据包才可以放入数据
	 * @param mixed $data
	 */
	public function setData($data) {
		if($this->type !== 'out') return;
		$this->data = json_encode($data);
		$this->dataLen = strlen($this->data);
	}

	/**
	 * 解析websocket数据帧
	 * @param string $buffer
	 */
	private function parse($buffer) {
		$buffer = unpack('H*', $buffer);
		$buffer = $buffer[1];
	
		$head = substr($buffer, 0,2);
		$this->opcode = hexdec($head{1});
		$len = 0;
		$data = null;

		if($this->opcode === self::$OPCODE_TEXT){
			$len = hexdec(substr($buffer, 2, 2))&0x7f;// 提取数据包长度
			$pos = 4;
	
			if($len === 0x7e) {// 标志位0x7e, 后面2字节,16bit存放数据长度
				$len = hexdec(substr($buffer,4,2)) *0x100 + hexdec(substr($buffer, 6, 2));
				$pos += 4;
			}elseif($len === 0x7f){// 标志位0x7f 则使用了64bit存放数据长度，这里不处理
				return false;
			}else{
				// 否则代表数据实际长度
			}
	
			$mask = array();
			// 提取mask
			$mask[] = hexdec(substr($buffer, $pos, 2));
			$mask[] = hexdec(substr($buffer, $pos+2, 2));
			$mask[] = hexdec(substr($buffer, $pos+4, 2));
			$mask[] = hexdec(substr($buffer, $pos+6, 2));
			$pos +=8;
	
			$data = array();
			for($i=0,$j=0; $i<$len; ++$i) {
				$j = $i%4;
				$data[] = chr(hexdec(substr($buffer, $pos+$i*2, 2)) ^ $mask[$j]);
			}
			$data = implode('', $data);
			$this->data = substr($data, 0, $len);
			$this->dataLen = $len;
		}
	}
}

/**
 * websocket 关闭连接帧
 */
class WsCloseFrame extends WsFrame {
	function __construct() {
		$this->fin = 1;
		$this->opcode = self::$OPCODE_CLOSE;
	}
}