<?php

/**
 * 自动签到脚本
 * by @cjli
 * Current version: v1.0.0
 */

# 定义所需配置常量
include 'config.php' ;

define('ZMZ_RMB'   , 1)            ;
define('ZMZ_SIGN'  , true)         ;
define('ZMZ_HOTKEY', true)         ;


# 定义全局变量
$is_zimuzu = 0 ;
$zmz_last  = 0 ;

$is_v2ex   = 0 ;
$v2_last   = 0 ;

date_default_timezone_set('Asia/Shanghai') ;

# cookie 生存时间规定为 12 小时 新生成的 cookie 从 0 计算时间
$v2_cookie_life     = filemtime('v2ex.cookie')   ;
$zimuzu_cookie_life = filemtime('zimuzu.cookie') ;
$time               = time() ;
if (($time - $zimuzu_cookie_life) >= 43200) {
	file_put_contents('zimuzu.cookie', '') ;
}
if (($time - $v2_cookie_life) >= 43200) {
	file_put_contents('v2ex.cookie', '') ;
}

$login_log = json_decode(file_get_contents('login-bot.json'), true) ;

# 如果 JSON 文件为空则按默认配置纪录一次以供判断
if (empty($login_log)) {
	# 记录登录信息
	logInfo($is_zimuzu, $is_v2ex, $zmz_last, $v2_last) ;
}

# 每 24 小时强制性清除 cookie 和重置登录状态为 0
$pre_day  = $login_log['date'] ;
$next_day = date('Y-m-d',strtotime('+1 day',strtotime($pre_day))) ;
if (date('Y-m-d', $time) == $next_day) {
	file_put_contents('zimuzu.cookie', '') ;
	file_put_contents('v2ex.cookie', '') ;
	$zmz_last = $login_log['last']['zimuzu'] ;
	$v2_last  = $login_log['last']['v2ex']   ;
	logInfo($is_zimuzu, $is_v2ex, $zmz_last, $v2_last) ;
}

# 判断日期是否正确
$is_legal_date = (strtotime(date('Y-m-d')) >= strtotime($login_log['date'])) ? true : false ;
if ($is_legal_date) {
	# 如果今天没有在字幕组签到过则登录字幕组并签到
	if (0==$login_log['is_zimuzu']) {
		zimuzuLogin(ZMZ_USER, ZMZ_PWD, ZMZ_RMB) ;
		# 未进行 v2ex 登录判断之前纪录字幕组登录信息的时候使用 json 文件中的信息更新
		$is_v2ex = $login_log['is_v2ex'] ;
		$v2_last = $login_log['last']['v2ex'] ;
		# 刷新登录信息
		logInfo($is_zimuzu, $is_v2ex, $zmz_last, $v2_last) ;
	} else {
		# 若已登录字幕组 则纪录 v2ex 登录信息的时候 字幕组的信息直接从 json 中取即可
		# 否则在纪录 v2ex 登录信息时候字幕组信息将被重置
		$is_zimuzu = $login_log['is_zimuzu'] ;
		$zmz_last  = $login_log['last']['zimuzu'] ;
	}

	if (0==$login_log['is_v2ex']) {
		v2exLogin(V2_MMBR, V2_PWD) ;	
		# 刷新登录信息
		logInfo($is_zimuzu, $is_v2ex, $zmz_last, $v2_last) ;
	}
}

/**
 * 字幕组登录
 * @param $account
 * @param $password
 * @param $remeber
 * @return Boolean
 */
function zimuzuLogin( $account, $password, $remember ) {
	global $is_zimuzu, $zmz_last ;

	# 1. 先登录获得 cookie
	$ajax_url = 'http://www.zimuzu.tv/User/Login/ajaxLogin' ;    // zimuzu.tv 实际登录地址
	$_cookie  = 'zimuzu.cookie' ;    // cookie 保存的文件
	$ch   = curl_init() ;
	$info = array(
	    'account'  => $account  ,
	    'password' => $password ,
	    'remember' => $remember
	) ;

	# 设置传输选项
	curl_setopt($ch, CURLOPT_URL, $ajax_url) ;
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE) ;
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('description:just sign in, do not block please.')) ;
	curl_setopt($ch, CURLOPT_POST, 1) ;    // 启用POST提交
	curl_setopt($ch, CURLOPT_POSTFIELDS, $info ) ;

	curl_setopt($ch, CURLOPT_COOKIEFILE, $_cookie) ;    // 包含 cookie 数据的文件名
    curl_setopt($ch, CURLOPT_COOKIEJAR,  $_cookie) ;    // 连接结束后保存 cookie 信息的文件

	$login_res = json_decode(curl_exec($ch), true) ;
	logError('zimuzu.tv', 'login', $ch) ;

	// print_r($login_res);

	# 2. 登录成功后带着第一步生成的 cookie 获得会员信息
	$cookie   = file_get_contents( 'zimuzu.cookie' ) ;

	if ( ZMZ_SIGN ) {
		# 获得会员登录信息
		$sign_url = 'http://www.zimuzu.tv/user/sign' ;    // 字幕组会员登录信息地址
		curl_setopt($ch, CURLOPT_COOKIE, $cookie) ;
	    curl_setopt($ch, CURLOPT_URL, $sign_url) ;
	    curl_setopt($ch, CURLOPT_POSTFIELDS, null) ;

	    $sign_res = json_decode(curl_exec($ch), true) ;
	    logError('zimuzu.tv', 'get user sign info', $ch) ;

		// print_r($sign_res);
	}

	if ( ZMZ_HOTKEY ) {
		# 获得字幕组热门搜索关键词
		$hotkey_url = 'http://www.zimuzu.tv/public/hotkeyword' ;   // 字幕组热门搜索关键词地址
		curl_setopt($ch, CURLOPT_COOKIE, $cookie) ;
	    curl_setopt($ch, CURLOPT_URL, $hotkey_url) ;
	    curl_setopt($ch, CURLOPT_POSTFIELDS, null) ;

	    $hotkey_res = json_decode(curl_exec($ch), true) ;
	    logError('zimuzu.tv', 'get hotkey words', $ch) ;

		// print_r($hotkey_res);
	}

	# 获得会员个人信息
	// $user_url = 'http://www.zimuzu.tv/user/login/getCurUserTopInfo' ;   // 字幕组登录会员个人信息地址
	$user_url = 'http://www.zimuzu.tv/user/user' ;   // 字幕组登录会员个人信息地址
	curl_setopt($ch, CURLOPT_COOKIE, $cookie)  ;
    curl_setopt($ch, CURLOPT_URL, $user_url)   ;
    curl_setopt($ch, CURLOPT_POSTFIELDS, null) ;

    // $user_res = json_decode(curl_exec($ch), true) ;
    $user_res = curl_exec($ch) ;
    logError('zimuzu.tv', 'get user info', $ch) ;

	# 从个人信息页面中筛选出连续签到天数
	$match    = array() ;
	$pattern  = '/已连续签到：<\/span><font class="f_u0">\d+ *天/' ;
	if (preg_match( $pattern, $user_res, $match)) {
		$zmz_last = explode('">', $match[0]) ;
		$zmz_last = explode(' ' , $zmz_last[1])[0] ;
		$zmz_last = intval(trim($zmz_last)) ;
	}

	// print_r($zmz_last);
    // print_r($user_res);

	# 关闭 CURL 连接
	curl_close($ch) ;

	# 如果登录成功则记录今天已签到过
	$is_zimuzu =  (1 == $login_res['status']) ? 1 : 0 ;
	return (1 == $login_res['status']) ? true : false ;
}

/**
 * V2EX 登录
 * @param $username
 * @param $password
 */
function v2exLogin($username, $password) {
	global $is_v2ex, $v2_last ;
	$v2_index = 'http://v2ex.com' ;

	# 1. 请求登录界面并获得登录界面的 input name + cookie + 登录所需 once 值
	$login_url = 'http://v2ex.com/signin' ;
	$header = array(
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.71 Safari/537.36',
	        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
	        'Referer: http://www.v2ex.com/signin',
	        'Origin: http://www.v2ex.com'
			) ;
	$_cookie   = 'v2ex.cookie' ;    // cookie 保存的文件

	$ch      = curl_init() ;
	$options = array(
		CURLOPT_HTTPHEADER     => $header ,
        CURLOPT_AUTOREFERER    => 1 ,    // 开启重定向
        CURLOPT_FOLLOWLOCATION => 1 ,    // 是否抓取跳转后的页面
        CURLOPT_HEADER         => 1 ,    // 启用时会将头文件的信息作为数据流输出
        CURLOPT_RETURNTRANSFER => 1 ,    // 将 curl_exec() 获取的信息以文件流的形式返回，而不是直接输出
    ) ;

	curl_setopt($ch, CURLOPT_URL, $login_url)      ;
	curl_setopt_array($ch, $options)               ;
	curl_setopt($ch, CURLOPT_COOKIEFILE, $_cookie) ;    // 包含 cookie 数据的文件名
    curl_setopt($ch, CURLOPT_COOKIEJAR,  $_cookie) ;    // 连接结束后保存 cookie 信息的文件
    $login_html = curl_exec($ch) ;
    logError($v2_index, 'GET '.$login_url, $ch) ;

    # 获得登录所需 once 值
    $matches = array() ;    // 保存登录名和密码的 input name
    $match   = array() ;    // 保存 once 值
    $pattern = '/([0-9A-Za-z]{64})/' ;
    if (preg_match_all($pattern, $login_html, $matches)) {
    	$input_user = $matches[0][0] ;
    	$input_pwd  = $matches[1][1] ;
    }

    if (preg_match('/type="hidden" value="(\d){5}" name="once"/', $login_html, $match)) {
    	$once = intval(trim(explode('"', explode(' ', $match[0])[1])[1])) ;
    }

    # 获得登录 input 标签的 name 值
    if (!isset($input_user) || !isset($input_pwd) || !isset($once)) {
    	$input_user = isset($input_user) ? $input_user : '' ;
    	$input_pwd  = isset($input_pwd)  ? $input_pwd  : '' ;
    	$once       = isset($once)       ? $once       : '' ;
    	logError('get input parameters:', 'user='.$input_user.' pwd='.$input_pwd.' once='.$once, $ch, true) ;
    }

	# 2. 登录
	$info = array(
	    $input_user   => $username ,
	    $input_pwd    => $password ,
	    'once' => $once ,
	    'next' => '/'
	) ;

	# 设置传输选项
	curl_setopt($ch, CURLOPT_URL, $login_url) ;
	curl_setopt_array($ch, $options) ;
	curl_setopt($ch, CURLOPT_POST, 1) ;    // 启用POST提交

	# CURLOPT_POSTFIELDS 参数值可以是 urlencoded 后的字符串 也可以是数组
	# 如果 $info 是一个数组，Content-Type 头将会被设置成 multipart/form-data
	# !!! 所以 $info 是数组时不要在头部加入非 multipart/form-data 值的 Content-Type
	// curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($info) ) ;    // 生成 urlencodeed 字符串
	curl_setopt($ch, CURLOPT_POSTFIELDS, $info ) ;

	curl_setopt($ch, CURLOPT_COOKIEFILE, $_cookie) ;    // 带 cookie 请求页面
    curl_setopt($ch, CURLOPT_COOKIEJAR, $_cookie)  ;    // 连接结束后保存 cookie 信息的文件

	$login_res = curl_exec($ch) ;
	logError($v2_index, 'login', $ch) ;

	# 3. 登录成功后重新 GET 网站首页以更新 once 值和 cookie 供签到使用
	curl_setopt($ch, CURLOPT_URL, $v2_index) ;
	curl_setopt($ch, CURLOPT_HEADER, 1) ;
	curl_setopt($ch, CURLOPT_COOKIEFILE, $_cookie) ;
	curl_setopt($ch, CURLOPT_COOKIEJAR, $_cookie) ;
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1) ;
	$logined_index = curl_exec($ch) ;
	
	# 获得签到所需 once 值
	$sign_once_ptrn = '/once=(\d){5}/' ;
	$sign_once      = preg_match($sign_once_ptrn, $logined_index, $sign_once_arr) ;
	$sign_once      = intval(trim(explode('=', $sign_once_arr[0])[1])) ;

	# 4. 对签到页面执行两次 GET 操作
	$sign_in_url1 = 'http://v2ex.com/mission/daily/redeem' ;
	curl_setopt($ch, CURLOPT_URL, $sign_in_url1.'?once='.$sign_once) ;
	curl_setopt($ch, CURLOPT_HEADER, 1) ;
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header)  ;
	curl_setopt($ch, CURLOPT_COOKIEFILE, $_cookie) ;
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1)    ;
	$sign_in_res1 = curl_exec($ch) ;    // 登录成功后第一次 GET 请求结果
	logError($v2_index, 'GET '.$sign_in_url1, $ch) ;

	$sign_in_url2 = 'http://v2ex.com/mission/daily' ;
	curl_setopt($ch, CURLOPT_URL, $sign_in_url2) ;
	$sign_in_res2 = curl_exec($ch) ;    // 登录成功后第二次 GET 请求结果(签到)
	logError($v2_index, 'GET '.$sign_in_url2, $ch) ;

	# 5. 刷新签到信息
	$v2_last_ptrn = '/已连续登录 * (\d)+ *天/' ;
	$v2_last      = preg_match($v2_last_ptrn, $sign_in_res2, $v2_last_arr) ;
	$v2_last      = intval(trim(explode(' ', $v2_last_arr[0])[1])) ;
	$is_v2ex      = 1 ;

	# 6. 关闭 curl 连接句柄
	curl_close($ch) ;
}

/**
 * 纪录错误日志
 * @param $website
 * @param $error
 * @param $ch
 * @param Boolean true
 */
function logError($obj='website', $error, $ch, $force=false) {
	$http_res     = curl_getinfo($ch, CURLINFO_HTTP_CODE) ;

	# 如果 HTTP 请求错误 或 需要强制性记录其他错误时则写入错误日志
	if (200 != $http_res || $force) {
		$error = "\n".date('Y-m-d H:i:s').' => '.$obj.' '.$error.' error'."\n" ;
		file_put_contents('login-bot.log', $error, FILE_APPEND) ;
		curl_close($ch) ;
		die ;
	}
	return true ;    // 返回 true 代表请求未错误
}

/**
 * 登录情况记录
 * @param $is_zimuzu
 * @param $is_v2ex
 * @param $zmz_last
 * @param $v2_last
 */
function logInfo( $is_zimuzu, $is_v2ex, $zmz_last, $v2_last ) {
	date_default_timezone_set('Asia/Shanghai') ;
	$date = date('Y-m-d') ;
	$log  = <<< LOG
{"date":"$date","is_zimuzu":$is_zimuzu,"is_v2ex":$is_v2ex,"last":{"zimuzu":$zmz_last,"v2ex":$v2_last}}
LOG;

	file_put_contents( 'login-bot.json', $log ) ;
}
