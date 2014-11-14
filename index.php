<?php
/**************************************
 * L5 SNS 信令服务端入口(CLI)
 * @version 1.0
 * @author lonphy
 ***************************************/
define('APP', dirname(__FILE__));
set_time_limit(0);
ob_implicit_flush();
require_once(APP. '/core/Output.php');
Output::setDebug('on');
require_once(APP. '/core/Socket.php');
require_once(APP. '/app/WebSocket.php');
$APP = new WebSocket();
$APP->run();