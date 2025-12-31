from BCrpcTCP import *
from time import sleep

def connectedCB(rpc):
	print('已连接上回调')
	return

rpc = BCrpcTCP('127.0.0.1:8000', True)
# 配置回调心跳，空闲1秒，判定2秒，重试3次
rpc.setCbOption(1, 2, 3, connectedCB)

rpc.call(1, b'hello')
r, z = rpc.call(2, b'hello666666666')
print(r, z)

def msgloop(this, msg, out):
	print('回调消息到来', msg, out)
	return True

while rpc.waitMsgLoop(msgloop):
	print('已断开,1秒后再次重连')
	sleep(1);

print("用户手动退出消息循环")
