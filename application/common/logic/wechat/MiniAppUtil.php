<?php


namespace app\common\logic\wechat;

use app\common\util\TpshopException;

use think\Db;
 
/**
 * 小程序官方接口类
 */
class MiniAppUtil extends WxCommon
{
    private $config = []; //小程序配置

    public function __construct($config = null)
    {
        if ($config === null) {
            $wxPay = Db::name('plugin')->where(array('type'=>'payment','code'=>'miniAppPay'))->find();
            $config = unserialize($wxPay['config_value']);
        }
        $this->config = $config;
    }
    
    public function getWxUserInfo($code , $iv , $encryptedData , &$data){
            try{
                $appid = $this->config['appid'];
                $session = $this->getSessionInfo($code);
		        if ($session === false) {
                    throw new TpshopException("小程序登录失败", 0, ['status' => 0, 'msg' => $this->getError(), 'result' => '']);
                }
                $sessionKey = $session['session_key'];
                $pc = new WxBizDataCrypt($appid, $sessionKey);
                $errCode = $pc->decryptData($encryptedData, $iv, $data);
                return $errCode;
            }catch (TpshopException $t){
                throw $t;
            } 
    }
    /**
     * 获取小程序session信息
     * @param string $code 登录码
     */
    public function getSessionInfo($code)
    {
        $appId = $this->config['appid'];
        $appSecret = $this->config['appsecret'];
        if (!$appId || !$appSecret) {
            $this->setError('请检查后台是否配置appid和appsecret');
            return false;
        }
        
        $fields = [
            'appid' => $appId,
            'secret' => $appSecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code'
        ];
        $url = 'https://api.weixin.qq.com/sns/jscode2session';
        $return = $this->requestAndCheck($url, 'GET', $fields);
        if ($return === false) {
            $this->setError('小程序登录失败, errcode : '.$return['errcode'].', errmsg : '.$return['errmsg']);
            return false;
        }
        return $return;
    }


    /**
     * 获取小程序access_token
     * @return bool|mixed
     */
    public function getMinAppAccessToken(){
        $assecc_token = cache('xcx_access_token');
        if($assecc_token){
            return $assecc_token;
        }else{
            $appId = $this->config['appid'];
            $appSecret = $this->config['appsecret'];
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}";
            $return = $this->requestAndCheck($url, 'GET');
            if (isset($return['access_token'])) {
                cache('xcx_access_token',$return['access_token'],7000);
                return $return['access_token'];
            }
            return false; 
        }
    }


    /**
     * @param $access_token
     * @param $data
     * @return 二进制流数据 or false
     */
    public function getWecatCreateQrcode($access_token,$data){
        $url="https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token=".$access_token;
        return $this->requestMinAppQrcode($url, 'POST', $data);
    }
    
    
}