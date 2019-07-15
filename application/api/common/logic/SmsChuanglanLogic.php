<?php

namespace app\common\logic;

/**
 * Description of SmsLogic
 *
 * 短信类
 */
class SmsChuanglanLogic 
{


	//参数的配置 请登录zz.253.com 获取以下API信息 ↓↓↓↓↓↓↓
	const API_SEND_URL = 'http://smssh1.253.com/msg/send/json'; //创蓝发送短信接口URL
		
	const API_VARIABLE_URL = 'http://smssh1.253.com/msg/send/json';//创蓝变量短信接口URL

	const API_BALANCE_QUERY_URL = 'http://smssh1.253.com/msg/balance/json';//创蓝短信余额查询接口URL

	public function __construct($account,$password)
    {
		$this->API_ACCOUNT = $account; // 创蓝API账号
		$this->API_PASSWORD = $password;// 创蓝API密码
	}

	/**
	 * 发送短信
	 *
	 * @param string $mobile 		手机号码
	 * @param string $msg 			短信内容
	 * @param string $needstatus 	是否需要状态报告
	 */
	public function sendSMS( $mobile, $msg, $needstatus = 'true') {
		
		//创蓝接口参数
		$postArr = array (
			'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
			'msg' => urlencode($msg),
			'phone' => $mobile,
			'report' => $needstatus
        );
		$result = $this->curlPost( self::API_SEND_URL, $postArr);
		//var_dump($postArr);die();
		return $result;
	}
	
	/**
	 * 发送变量短信
	 *
	 * @param string $msg 			短信内容
	 * @param string $params 	最多不能超过1000个参数组
	 */
	public function sendVariableSMS( $msg, $params) {
		global $chuanglan_config;
		//创蓝接口参数
		$postArr = array (
			'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
			'msg' => $msg,
			'params' => $params,
			'report' => 'true'
        );
		
		$result = $this->curlPost( self::API_VARIABLE_URL, $postArr);
		return $result;
	}
	
	/**
	 * 查询额度
	 *
	 *  查询地址
	 */
	public function queryBalance() {
		global $chuanglan_config;
		
		//查询参数
		$postArr = array ( 
		    'account'  =>  $this->API_ACCOUNT,
			'password' => $this->API_PASSWORD,
		);
		$result = $this->curlPost(self::API_BALANCE_QUERY_URL, $postArr);
		return $result;
	}
	/**
	 * 通过CURL发送HTTP请求
	 * @param string $url  //请求URL
	 * @param array $postFields //请求参数 
	 * @return mixed
	 *  
	 */
	private function curlPost($url,$postFields){
		$postFields = json_encode($postFields);
		
		$ch = curl_init ();
		curl_setopt( $ch, CURLOPT_URL, $url ); 
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json; charset=utf-8'   //json版本需要填写  Content-Type: application/json;
			)
		);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt( $ch, CURLOPT_TIMEOUT,60); 
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);
		$ret = curl_exec ( $ch );
        if (false == $ret) {
            $result = curl_error(  $ch);
        } else {
            $rsp = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
		curl_close ( $ch );
		return $result;
	}

}