import socket
import struct

class BCtcp:
	def __init__(self, host):
		self.ip, self.port = host.rsplit(":", 1)
		self.tcp = None
	def isconed(self):
		return self.tcp != None
	def connect(self):
		try:
			if (self.isconed()): self.close()  # 连接超时5秒一般是够用的
			self.tcp = socket.create_connection((self.ip, self.port), 5)
		except:
			self.tcp = None
	def setTimeout(self, s):
		try:
			self.tcp.settimeout(s)
		except:
			self.tcp = None
	def send(self, dat):
		try:
			self.tcp.sendall(dat)
		except:
			self.tcp = None
	def recv(self, size):
		return self.tcp.recv(size)
	def close(self):
		if (self.tcp):
			self.tcp.close()
			self.tcp = None
	def __exit__(self):
		self.close()

class BCrpcTCP:
	def __init__(self, host, isRegCB=False, timeout=60):
		self.defTimeout = timeout
		self.tcp = BCtcp(host)
		self.tcpCB = BCtcp(host)
		self.host = host;
		self.cbsock = 0  # 服务器返回的客户回调socketID
		self.isRegCB = isRegCB;
		self.idleTime = self.liveTime = self.liveCount = self.conedCB = None

	def _connect(self):
		if (self.isRegCB and self._registerCb() == False):
			return False
		if (self.tcp.isconed()): return True;
		if (self.tcp.connect() == False): return False
		return True
	def _send(self, msg, data, timeout):
		timeout = self.defTimeout if (timeout == None) else timeout;
		self.tcp.setTimeout(timeout)
		self.tcp.send(data)
		if (msg < 0):  # 异步发送
			return 0, b''
		try:
			r, size = struct.unpack('ll', self.tcp.recv(8));
			retnEx = b'';
			while len(retnEx) < size:
				chunk = self.tcp.recv(size - len(retnEx))
				if chunk == None:
					return -1024, b''
				retnEx += chunk
		except:
			return -1024, b''
		return r, retnEx

	def call(self, msg, param=b'', timeout=None):
		if (self._connect() == False):
			return -1024, b'';
		buf = struct.pack('llQ', len(param), msg, self.cbsock) + param
		return self._send(msg, buf, timeout)

	def getCb(self):
		return self.cbsock;
	def setCbOption(self, idleTime, liveTime, liveCount, conedCB=None, newcb=None):
		self.idleTime = idleTime
		self.liveTime = liveTime
		self.liveCount = liveCount
		self.conedCB = conedCB
		if (newcb): self.cbsock = newcb

	#执行完后一定代表着回调客户句柄的断开，之后可重复调用
	def waitMsgLoop(self, msgrecv):
		while True:
			if (self._registerCb() == False):
				return True
			self.tcpCB.setTimeout(self.idleTime)
			try:
				msg, size = struct.unpack('ll', self.tcpCB.recv(8))
				out = b''
				while len(out) < size:
					chunk = self.tcpCB.recv(size - len(out))
					if chunk == None:
						self._closeCB()
						return True
					out += chunk
				if (msgrecv(self, msg, out) == False):
					self.tcpCB.close()  #用户强制关闭
					return False
			except Exception as e:
				if (str(e) == 'timed out' and self._heartbeat() != False):
					continue
				# 非超时一定是套接字异常了
				self.tcpCB.close()
				return True

	def _registerCb(self):
		if (self.tcpCB.isconed()):
			return True
		try:
			self.tcpCB.connect()
			self.tcpCB.setTimeout(self.defTimeout)
			self.tcpCB.send(b"TCP /registerCB \r\n\r\n");
			self.cbsock = self.tcpCB.recv(8);
			self.cbsock = struct.unpack('Q', self.cbsock)[0]
			self.tcpCB.send(b"TCP /chunkedCB \r\n\r\n");
			self.conedCB and self.conedCB(self)
			return self.cbsock != 0
		except:
			return False

	def _heartbeat(self):
		liveCount = self.liveCount;
		tcp = BCtcp(self.host)
		tcp.setTimeout(self.liveTime)
		while liveCount > 0:
			if (tcp.isconed() == False):
				tcp.connect()
			try:
				tcp.send(struct.pack('llQ', 0, 0, self.cbsock))
				r, _ = struct.unpack('ll', tcp.recv(8))
			except:
				r = -1024
			if (r == -1024):  #超时则继续发
				liveCount -= 1
			else:
				return r == 1 #只有为1才是服务端存在
		return False

	def __exit__(self):
		self.tcp.close()
		self.tcpCB.close()
