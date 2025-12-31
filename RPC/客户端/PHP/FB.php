<?php

// region 解包整数类型的声明常量
const I_sign=-1;
const I_usign=1;
// endregion

// region 类中定义类型的声明常量
const T_char = -1;
const T_short = -2;
const T_int = -4;
const T_int64 = -8;
const T_uchar = 1;
const T_ushort = 2;
const T_uint = 4;
const T_bool = false;
const T_float = 4.0;
const T_double = 8.0;
const T_String = '';
const T_Bytes = '';
// endregion


// region 公开的使用接口
function FB(...$arr)
{
	$n = count($arr);
	$ks = [$n];
	$k = ($n+2)*4;
	foreach ($arr as &$v){
		$ks[] = $k;
		if (is_array($v)){
			$k += _arr_size($v);
		} elseif (is_string($v)){
			$k += strlen($v);
		} elseif (is_float($v)){
			$k += 8;
		} elseif (is_bool($v)){
			$k += 1;
		} else{
			$k += 4;
		}
	}
	$ks[] = $k;
	$out = pack('l*', ...$ks);
	foreach ($arr as &$v){
		if (is_array($v)){
			_arr_set($v, $out);
		} elseif (is_string($v)){
			$out .= $v;
		} elseif (is_float($v)){
			$out .= pack('d', $v);
		} elseif (is_bool($v)){
			$out .= pack('C', $v);
		} else{
			$out .= pack('l', $v);
		}
	}
	return $out;
}

function deFB(&$dat, &...$arr)
{
	$i = 0;
	foreach ($arr as &$v){
		if (is_array($v)){
			_arr_get(gFB($dat, $i), $v);
		} elseif (is_string($v)){
			$v = gFB($dat, $i);
		} elseif (is_float($v)){
			$v = gFBf($dat, $i);
		} elseif(is_bool($v)){
			$v = gFBb($dat, $i);
		}else{
			$v = gFBi($dat, $i, $v<0);
		}
		$i++;
	}
}

function& gFB(&$dat, $i)
{
	$p = unpack('L2', $dat, 4*($i+1));
	$o = substr($dat, $p[1], $p[2]-$p[1]);
	return $o;
}

function gFBi(&$dat, $i, $signed = true)
{
	$p = unpack('L2', $dat, 4*($i+1));
	$size = $p[2]-$p[1];
	$is = $size == 4? 'l':($size == 1? 'c':
		($size == 2? 's':'q'));
	if (!$signed) $is = strtoupper($is);
	return unpack($is, $dat, $p[1])[1];
}

function gFBf(&$dat, $i)
{
	$p = unpack('L2', $dat, 4*($i+1));
	return unpack($p[2]-$p[1] == 4? 'f':'d', $dat, $p[1])[1];
}

function gFBb(&$dat, $i)
{
	$p = unpack('L', $dat, 4*($i+1));
	return unpack('C', $dat, $p[1])[1]!=0;
}

function gFB_n(&$dat)
{
	return unpack('L', $dat)[1];
}

function gFB_p(&$dat, $i, &$size)
{
	$p = unpack('L2', $dat, 4*($i+1));
	$size = $p[2]-$p[1];
	return $p[1];
}
// endregion


// region 对PHP不支持的数值类型提供临时封包方案
function i8($a)
{
	return pack('c', $a);
}
function i16($a)
{
	return pack('s', $a);
}
function i32($a)
{
	return pack('l', $a);
}
function i64($a)
{
	return pack('q', $a);
}
function f32($a)
{
	return pack('f', $a);
}
function arr_i8(&$arr, $sign = true)
{
	return pack('l', count($arr)).pack('c*', ...$arr).$sign? "\xFF":"\x1";
}
function arr_i16(&$arr, $sign = true)
{
	return pack('l', count($arr)).pack('s*', ...$arr).$sign? "\xFE":"\x2";
}
function arr_i32(&$arr, $sign = true)
{
	return pack('l', count($arr)).pack('s*', ...$arr).$sign? "\xFC":"\x4";
}
function arr_i64(&$arr, $sign = true)
{
	return pack('l', count($arr)).pack('q*', ...$arr)."\x8";
}
function arr_f32(&$arr)
{
	return pack('l', count($arr)).pack('f*', ...$arr).'f';
}
// endregion

// region 内部工具函数
function _arr_size(&$arr)
{
	$size = count($arr);
	if ($size == 0) return 5;
	if (is_string($arr[0])){
		$size = 4*(1+$size);
		foreach ($arr as &$v) $size += strlen($v);
	} elseif (is_float($arr[0])){
		$size *= 8;
	} else{
		$size *= 4;
	}
	return 4+$size+1;
}

function _arr_get($dat, &$out)
{
	$out = [];
	$n = unpack('L', $dat)[1];
	if ($dat[-1] == 's' || $dat[-1] == 'b'){
		for ($i = 0; $i<$n; $i++){
			$out[] = gFB($dat, $i);
		}
	} elseif ($dat[-1] == "\x1"){
		$out = array_values(unpack("C$n", $dat, 4));
	} elseif ($dat[-1] == "\x2"){
		$out = array_values(unpack("S$n", $dat, 4));
	} elseif ($dat[-1] == "\x4"){
		$out = array_values(unpack("I$n", $dat, 4));
	} elseif ($dat[-1] == "\xFF"){
		$out = array_values(unpack("c$n", $dat, 4));
	} elseif ($dat[-1] == "\xFE"){
		$out = array_values(unpack("s$n", $dat, 4));
	} elseif ($dat[-1] == "\xFC"){
		$out = array_values(unpack("i$n", $dat, 4));
	} elseif ($dat[-1] == "\x8"){
		$out = array_values(unpack("q$n", $dat, 4));
	} elseif ($dat[-1] == 'f'){
		$out = array_values(unpack("f$n", $dat, 4));
	} elseif ($dat[-1] == 'd'){
		$out = array_values(unpack("d$n", $dat, 4));
	}
}

function _arr_set(&$arr, &$out)
{
	$size = count($arr);
	if ($size == 0) return "\0\0\0\0\0";
	$out .= pack('L', $size);
	if (is_string($arr[0])){
		$size = 4*(1+$size);
		$i = strlen($out);
		$out .= str_repeat("\0", $size);
		$size += 4;
		_replace_substr($out, pack('L', $size), $i);
		foreach ($arr as &$v){
			_replace_substr($out, pack('L', $size += strlen($v)), $i += 4);
			$out .= $v;
		}
		$out .= 'b';
	} elseif (is_float($arr[0])){
		$out .= pack('d*', ...$arr).'d';
	} else{
		$out .= pack('l*', ...$arr)."\x4";
	}
}

function _replace_substr(&$str, $rpl, $start = 0)
{
	$len = strlen($rpl);
	for ($i = 0; $i<$len; $i++){
		$str[$start+$i] = $rpl[$i];
	}
}

function Arr($v, $count)
{
	return array_fill(0, $count, $v);
}
// endregion

// region 附赠构造字节集和解字节集方法
if (!function_exists('zjj')){
	function zjj() //多变参，直接投入每个字节值
	{
		return call_user_func_array('\pack',
			array_merge(array("C*"), func_get_args()));
	}

	function jzjj($asd)
	{
		return 'Bytes:'.strlen($asd).'{'.join(',', unpack("C*", $asd))."}";
	}
}
// endregion
