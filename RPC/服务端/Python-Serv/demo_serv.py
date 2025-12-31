from BCServerRPC import *

async def mycb(cbsock):
	print('异步一秒后执行')
	BCS_cbClient(cbsock, 666, bytes('我一秒后才给你发回调数据', 'utf-8'))
	return

async def BC_CALLBAKE(hcb, msg, param, size):
	print("数据到达", msg, param, size)
	BCS_AsyncPost(1000, mycb, hcb)  # 异步投递回调函数，该执行后立即返回
	await BCS_CoSleep(500) #协程延迟500ms
	return BCS(msg, bytes('服务器返回了字节集数据', 'utf-8'))

def on_header(header, size, sock):
	if(size==0): #心跳包
		return True;
	print("头部到达", header, size)  #通过size去限定最大报文长度
	return True  #返回真则放行，返回假则关闭连接

BCServerRPC(BC_CALLBAKE, "0.0.0.0:8000", on_header)
