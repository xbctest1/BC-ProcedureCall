<?php
include_once 'FB.php';

class BCrpcHTTP
{
	public $host;
	private $ch, $cbch, $mh, $cbsock, $isRegCB, $recvtime = 0, $defTimeout;

	function __construct($host, $isRegCB = false, $timeout = 60)
	{
		$this->host = "http://$host/";
		$ch = $this->ch = curl_init($this->host);
		$this->mh = curl_multi_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$this->isRegCB = $isRegCB;
		$this->defTimeout = $timeout;
	}

	function __destruct()
	{
		if ($this->ch) curl_close($this->ch);
		if ($this->cbch) curl_close($this->cbch);
		if ($this->mh) curl_multi_close($this->mh);
	}

	/**执行远程call可等待返回或设定异步立即返回
	 * @param int $msg 当消息为负数时不等待服务端返回
	 * @param string $param 可为任意二进制数据
	 * @param string &$retnEx 返回扩展的字节集数据
	 * @return int|void 除了内置-1024表示失败外其他整型均可是服务端返回值，
	 *                  (此外当使用负数消息投递call时此返回值未定义)
	 */ //{0:binLen,4:cbsock,12:msg[,16:param]}
	function call($msg, $param = '', &$retnEx = '', $timeout = null)
	{
		if ($this->isRegCB && $this->_registerCB() === false) {
			$retnEx = '';
			return -1024;
		}
		$buf = pack('llQ', strlen($param), $msg, $this->cbsock) . $param;
		return $this->_send($msg, $buf, $retnEx, $timeout);
	}

	function callA($msg, &$retnEx = '', ...$args)
	{
		return $this->call($msg, FB(...$args), $retnEx);
	}

	private function _send($msg, &$buf, &$retnEx, $timeout)
	{
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, $buf);
		if ($msg < 0) { //使用仅投递模式而不等待服务端返回
			curl_multi_add_handle($this->mh, $this->ch);
			curl_multi_exec($this->mh, $active);
			curl_multi_remove_handle($this->mh, $this->ch);
			return;
		}
		if ($timeout == null) $timeout = $this->defTimeout;
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
		$out = curl_exec($this->ch);
		if ($out === false) {
			$retnEx = '';
			return -1024;
		}
		$retnEx = substr($out, 8);
		return unpack('l', $out)[1];
	}

	function getCb()
	{
		return $this->cbsock;
	}

	/**设置回调客户的配置(以下时间单位都是秒)
	 * @param int $recvtime =-1 从接收回调开始可一直持续的HTTP整个流会话时间（单位是秒）
	 * @param $newcb =null 如果填写将改变当前对象所使用的回调句柄(可由不同RPC对象的getcb()提供)
	 */
	function setCbOption($recvtime = -1, $newcb = null)
	{
		if ($recvtime > 0) $this->recvtime = $recvtime;
		if ($newcb) $this->cbsock = $newcb;
	}

	/**执行完后一定代表着回调客户句柄的断开，之后可重复调用
	 * @param callable $msgrecv ($self,$msg,$dat)
	 */
	function waitMsgLoop(callable $msgrecv)
	{
		if ($this->_registerCB() == false) return true;
		$userExit = false;
		curl_setopt($this->cbch, CURLOPT_TIMEOUT, $this->recvtime);
		curl_setopt($this->cbch, CURLOPT_WRITEFUNCTION,
			function ($ch, $data) use ($msgrecv, &$userExit) {
				if ($msgrecv($this, unpack('l', $data)[1], substr($data, 8))
					=== false) {
					$userExit = true;
					return false;
				}
				return strlen($data);
			});
		curl_exec($this->cbch); //超时后必会断开无论有没有close
		curl_close($this->cbch);
		$this->cbch = null;
		return !$userExit;
	}

	private function _registerCB()
	{
		if (!$this->cbch) {
			$this->cbch = curl_init($this->host);
			curl_setopt($this->cbch, CURLOPT_RETURNTRANSFER, true);
			$r = curl_exec($this->cbch);
			if ($r == null) return false;
			$this->cbsock = unpack('Q', $r)[1];
		}
		return true;
	}

	static function exec($exe, $cmd)
	{
		if (class_exists('Swoole\\Process')) {
			$exec = new Swoole\Process(function ($p)
			use ($exe, $cmd) {
				$p->exec($exe, [$cmd]);
			}, true, 0, false);
			$exec->start();
		} else {
			exec("$exe $cmd");
		}
	}
}

function init_serv_push($最大时长 = 60 * 30)
{
	set_time_limit($最大时长);
	@ob_end_clean();
	header('X-Accel-Buffering: no');
	ob_implicit_flush();
}
