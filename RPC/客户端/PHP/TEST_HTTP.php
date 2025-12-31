<?php
include 'BCrpcHTTP.php';

$rpc = new BCrpcHTTP('127.0.0.1:8000', 1);
$rpc->call(1, 'hello');
echo $rpc->call(2, str_repeat('hello', 1024));
$rpc->setCbOption(60); //HTTP_Tunck流不能像TCP那样有较高级别的自定义心跳

$rpc->waitMsgLoop(function ($self, $msg, $dat) {
	echo $msg, ":", $dat,"\n";
	if ($msg == -1){ //可约定一个消息号退出
		return false;
	}
	return true;
});
