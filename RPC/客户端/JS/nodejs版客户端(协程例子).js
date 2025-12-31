const {BCrpcWS_Co, b, b2s, pack, unpack} = require('./BCrpcWS_Co.js');

var rpc = new BCrpcWS_Co('127.0.0.1:8000', 2);

rpc.setcbOption(function () {
	console.log('首次连接上(包括重连)');
}, 2, 1, 1);

function msgloop(self, r, dat, len) {
	console.log(r, dat);
	return true;
}

rpc.waitMsgLoop(msgloop, function (isContinue) {
	if (!isContinue) {
		console.log('用户手动退出消息循环');
		return;
	}
	console.log('已断开,1秒后再次重连');
	this.resumeMsgLoop(1);
});

// 协程化调用
async function testCall(msg) {
	//返回值必须严格投入名称{r,rx}，也可以仅投入一个{r}
	var {r, rx} = await rpc.call(msg, b('hello'));
	console.log(r, rx);
}

// 并发调用
console.log('下面是并发请求');
testCall(1);
testCall(2);
console.log('并发请求结束');


// 按协程顺序执行多个调用
async function runSequential() {
	console.log('开始第一个调用');
	var {r} = await rpc.call(111, b('hello')); //仅接收r
	console.log(`第一个调用完成(返回值:${r})，开始第二个调用`);
	await testCall(222);
	console.log('所有调用完成');
	//process.exit(); //nodejs底层自带消息循环，如果不手动退出进程但凡只要有异步监听的事件存在都会一直卡着
	rpc.close(true); //可以注释上边而试试这个，如果底层彻底清空了异步资源(包括套接字/时钟等)就会立即退出的
}
runSequential();
