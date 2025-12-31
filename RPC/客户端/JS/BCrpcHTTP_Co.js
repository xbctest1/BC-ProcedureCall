class BCrpcHTTP_Co {
	constructor(host, timeout) {
		this.host = 'http://' + host;
		this.timeout = (timeout || 60) * 1000;
	}

	async call(msg, param) {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			xhr.open('POST', this.host, true);
			xhr.responseType = 'arraybuffer';
			xhr.timeout = this.timeout;
			xhr.onload = function () {
				var res = xhr.response, len = res.byteLength;
				resolve({r: unpack('l', res), rx: res.slice(8, 8 + len)});
			};
			xhr.onerror = xhr.ontimeout = function () {
				resolve({r: -1024, rx: null});
			}
			xhr.send(pack('llbb', param.length, msg, new Uint8Array(8), param));
		});
	}
}


// region 工具方法
Uint8Array.prototype.concat = function (pArr) {
	if (!pArr) pArr = new Uint8Array();
	var rLen = this.length, arr = new Uint8Array(rLen + pArr.length);
	arr.set(this);
	arr.set(pArr, rLen);
	return arr;
};

function pack(format) {
	var result = new Uint8Array(0);
	var view, tbuf;
	for (var i = 0; i < format.length; i++) {
		var f = format.charAt(i);
		var value = arguments[i + 1];
		if (f === 'l') {
			tbuf = new Uint8Array(4);
			view = new DataView(tbuf.buffer);
			view.setInt32(0, value, true);
			result = result.concat(tbuf);
		} else if (f === 'b') {
			result = result.concat(value);
		}
	}
	return result;
}

function unpack(format, data, offset) {
	offset = offset || 0;
	var view = new DataView(data.buffer || data);
	if (format === 'l') {
		return view.getInt32(offset, true);
	} else if (format === 'Q') {
		return view.getInt32(offset, true);
	}
	return null;
}


function b(s) {
	var out = [];
	for (var i = 0; i < s.length; i++) {
		var code = s.charCodeAt(i);
		if (code < 0x80) {
			out.push(code);
		} else if (code < 0x800) {
			out.push(0xc0 | (code >> 6));
			out.push(0x80 | (code & 0x3f));
		} else if (code < 0xd800 || code >= 0xe000) {
			out.push(0xe0 | (code >> 12));
			out.push(0x80 | ((code >> 6) & 0x3f));
			out.push(0x80 | (code & 0x3f));
		} else {
			i++;
			var c2 = s.charCodeAt(i);
			var c = 0x10000 + (((code & 0x3ff) << 10) | (c2 & 0x3ff));
			out.push(0xf0 | (c >> 18));
			out.push(0x80 | ((c >> 12) & 0x3f));
			out.push(0x80 | ((c >> 6) & 0x3f));
			out.push(0x80 | (c & 0x3f));
		}
	}
	return new Uint8Array(out);
}

function b2s(buf) {
	var b = new Uint8Array(buf), s = '', i = 0;
	while (i < b.length) {
		var c = b[i++];
		s += c < 128
			 ? String.fromCharCode(c)
			 : c < 224 ? String.fromCharCode((c & 31) << 6 | b[i++] & 63)
				  : String.fromCharCode((c & 15) << 12 | (b[i++] & 63) << 6 | b[i++] & 63);
	}
	return s;
}

// endregion
