import asyncio
from struct import *

_cache_clients = {}

def jzjj(r): return '{' + ','.join([str(byte) for byte in r]) + '}';

class BCServerRPC:
	"""初始化并启动RPC服务器
	:param on_message: 回调处理函数(msg,param1,param2)
	:param bind_address: 绑定地址，格式为"ip:port"
	:param on_header: 头部到来的前置回调(header,size,sock):返回False则关闭当前连接（header=0表示这是一个注册回调）
	:param on_cbclose: 回调客户已关闭(cbsock)
	:param on_connected: TCP层面完成连接后首次获得套接字响应的回调(sock):返回0则确定关闭连接
	"""
	def __init__(self, on_message, bind_address, on_header=None, on_cbclose=None, on_connected=None):
		self.on_message = on_message
		self.on_header = on_header
		self.on_cbclose = on_cbclose
		self.on_connected = on_connected;
		self.host, port = bind_address.split(':')
		self.port = int(port)
		self.env = 1;
		asyncio.run(self.start())

	async def on_clientEntry(self, reader, writer):
		try:
			self.on_connected and self.on_connected(writer)
			while True:
				# 读取前16个字节的头部
				header = await reader.readexactly(16)
				sign = header[:4]
				if (sign == b'TCP ' or sign == b'GET '):
					# 表示这是一个注册回调
					self.env = -1 if sign == b'TCP ' else -2
					await self.registerCb(reader, writer)
					return

				isHttpPost = False;
				if (sign == b'POST'):
					# 检测到HTTP模式从POST中"绕过数据"
					await reader.readuntil(b"\r\n\r\n")
					header = await reader.read(16)
					isHttpPost = True

				# {0:binLen,4:msg,8:cbsock[,16:binParam]}
				binLen, msg, cbsock = unpack('llQ', header)

				# 头部到达提醒检测用户size
				if (self.on_header and self.on_header(header, binLen, writer) == False):
					writer.close()
					return

				# 继续读取TCP模式下的剩余数据
				rid = cbsock & 0xFFFFFFFF if cbsock >> 32 == 0xFFFFFFFF else 0  #消息对号模式
				param = b""
				remLen = binLen;
				while remLen > 0:
					chunk = await reader.read(remLen)
					param += chunk
					remLen -= len(chunk)

				if (msg != 0):
					rEx = await self.on_message(cbsock, msg, param, binLen)
				else:  #msg==0为心跳包
					r = 1 if _cache_clients.get(cbsock) != None else 0
					rEx = pack('ll', r, 0)

				if (rid != 0):
					rEx += pack('l', rid);  #消息对号模式
				if (isHttpPost):
					await self.httpResponse(writer, rEx)
				else:
					writer.write(rEx)
					await writer.drain()
		except Exception as e:
			#print(f"Error in handle_client: {e}")
			writer.close()
			None;

	async def registerCb(self, reader, writer):
		self.on_header and self.on_header(0, 0, writer)
		await reader.read(4096)  #读取足够大的字节数确保清空本次请求
		global _cache_clients
		cb = id(writer)
		_cache_clients[cb] = writer
		_cache_clients[cb].env = self.env
		if (self.env == -1):  #纯TCP模式
			writer.write(pack('Q', cb))
			await reader.read(4096)  #清空"TCP /chunkedCB"
		else:
			await self.httpResponse(writer, pack('Q', cb))  #先发回cbsock
			await reader.read(4096)  #等待下一个请求到来后就进入chunked模式
			await self.httpResponse(writer, None, True)

		try:
			await reader.read()  #只要能读说明已断开
		except:
			None
		writer.close()
		self.on_cbclose and self.on_cbclose(writer)
		_cache_clients.pop(cb, None)

	async def httpResponse(self, writer, dat=None, chunked=False):
		if (chunked):
			writer.write(b"HTTP/1.1 200 OK\r\n" +
			             b"Transfer-Encoding: chunked\r\n\r\n")
		else:
			writer.write(b"HTTP/1.1 200 OK\r\n" +
			             b'Access-Control-Allow-Origin:*\r\n' +
			             b"Content-Length: " + bytes(f"{len(dat)}", 'ascii') +
			             b"\r\n\r\n" + dat)
		await writer.drain()


	async def start(self):
		"""启动RPC服务器"""
		server = await asyncio.start_server(self.on_clientEntry, self.host, self.port)
		print(f"Server started on {self.host}:{self.port}")
		async with server:
			await server.serve_forever()

# region 公开全局接口
def BCS(retn: int, retnEx: bytes = b''):
	return pack('ll', retn, len(retnEx)) + retnEx

def BCS_cbClient(cbsock, msg, bin):
	global _cache_clients
	msg = pack('ii', msg, len(bin))
	try:
		if (_cache_clients[cbsock].env == -1):
			_cache_clients[cbsock].write(msg + bin)
		else:
			size = bytes(f"{8 + len(bin):x}", 'ascii')
			_cache_clients[cbsock].write(size + b"\r\n" + msg + bin + b"\r\n")
		return True
	except:
		return False

async def _delayed_task1(callback, delay, paramInt):
	await asyncio.sleep(delay)
	await callback(paramInt)  # 延迟后执行回调函数

async def _delayed_task2(callback, delay, paramInt, paramBin):
	await asyncio.sleep(delay)
	await callback(paramInt, paramBin)  # 延迟后执行回调函数

def BCS_AsyncPost(delay, callback, paramInt=None, paramBin=None):
	"""
	:param delay: 延迟执行的毫秒数
	:param callback: 如果不需要bin则只需1个参数接收int；有bin时参数1为int,参数2为Bin数据
	:param paramInt: 可空
	:param paramBin: 可空
	:return:
	"""
	delay = delay / 1000
	if (paramBin == None):
		asyncio.create_task(_delayed_task1(callback, delay, paramInt))
	else:
		asyncio.create_task(_delayed_task2(callback, delay, paramInt, paramBin))

async def BCS_CoSleep(delay):
	await asyncio.sleep(delay / 1000)
# endregion
