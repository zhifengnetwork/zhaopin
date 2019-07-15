<?php


namespace app\common\logic\saas\wechat;

use app\common\model\saas\Wx3rd;
use app\common\util\XML;

/**
 * 微信小程序第三方平台操作类
 * 单例模式
 */
class Wx3rdPlatform extends WxCommon
{
    static private $install = null; //实例
    private $config = []; //第三方平台配置

    //授权事件类型
    const AUTH_EVENT_ALL = 0; //全局事件
    const AUTH_EVENT_VERIFY_TICKET = 1; //开放平台的ticket推送通知,微信会10分钟推一次
    const AUTH_EVENT_UNAUTHORIZED = 2; //取消授权事件
    const AUTH_EVENT_AUTHORIZED = 3; //授权事件
    const AUTH_EVENT_UPDATE_AUTHORIZED = 4; //更新授权事件

    //普通事件类型
    const COMMON_EVENT_ALL = 0; //全局事件
    const COMMON_EVENT_WEAPP_AUDIT_SUCCESS = 1; //小程序审核成功事件
    const COMMON_EVENT_WEAPP_AUDIT_FAIL = 2; //小程序审核失败事件


    private function __construct($config = null)
    {
        if (!$config && !$this->config) {
            $config = Wx3rd::get(1);
        }
        $this->config = $config ?: $this->config;
    }
    
    /**
     * 获取实例
     * @param array $config 如果为空，则自动获取
     * @return self
     */
    static public function getInstance($config = null)
    {
        if (!self::$install) {
            self::$install = new self($config);
        }
        return self::$install;
    }
    
    public function getConfig()
    {
        return $this->config;
    }
    
    public function setConfig($config)
    {
        $this->config = $config;
    }
    
    /**
     * 获取第三方平台令牌（组件方令牌）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
     * @return string
     */
    public function getComponentAccessToken()
    {
        $config = $this->config;
        if (empty($config)) {
            $this->setError("第三方平台信息未配置");
            return false;
        }

        //判断是否过了缓存期
        if ($config['access_token'] && $config['access_token_expires'] > time()) {
           return $config['access_token'];
        }
        
        $post = $this->toJson([
            'component_appid'       => $config['appid'] ,
            'component_appsecret'   => $config['appsecret'], 
            'component_verify_ticket' => $config['verify_ticket']
        ]);
        $url = "https://api.weixin.qq.com/cgi-bin/component/api_component_token";
        $return = $this->requestAndCheck($url, 'POST', $post);
        if ($return === false) {
            $this->config['access_token_expires'] = 0;
            Wx3rd::update(['access_token_expires' => 0], ['id' => $config['id']]);//token错误
            return false;
        }
        
        $expires = time() + $return['expires_in'] - 200; // 提前200秒过期
        Wx3rd::update([
            'access_token' => $return['component_access_token'],
            'access_token_expires'=> $expires
        ], ['id' => $config['id']]);
        $this->config['access_token'] = $return['component_access_token'];
        $this->config['access_token_expires'] = $expires;
        
        return $return['component_access_token'];
    }
    
    /**
     * 获取预授权码pre_auth_code
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
     * @return mixed 预授权码
     */
    public function getPreAuthCode()
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token={$accessToken}";        
        $post = $this->toJson([
            'component_appid' => $this->config['appid'],
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        return $wxdata['pre_auth_code'];
    }
    
    /**
     * 使用授权码换取公众号或小程序的接口调用凭据和授权信息
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
     * @param string $authCode 授权码
     * @return mixed 授权数组
     */
    public function getAuthInfo($authCode)
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token={$accessToken}";        
        $post = $this->toJson([
            'component_appid'    => $this->config['appid'],
            'authorization_code' => $authCode
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        //返回数据结构
        //{ 
        //    "authorization_info": {
        //        "authorizer_appid": "wxf8b4f85f3a794e77", 
        //        "authorizer_access_token": "QXjUqNqfYVH0yBE1iI_7vuN_9gQbpjfK7hYwJ3P7xOa88a89-Aga5x1NMYJyB8G2yKt1KCl0nPC3W9GJzw0Zzq_dBxc8pxIGUNi_bFes0qM", 
        //        "expires_in": 7200, 
        //        "authorizer_refresh_token": "dTo-YCXPL4llX-u1W1pPpnp8Hgm4wpJtlR6iV0doKdY", 
        //        "func_info": [
        //            {
        //                "funcscope_category": {
        //                    "id": 1
        //                }
        //            }
        //        ]
        //    }
        //}
        return $wxdata['authorization_info'];
    }

    /**
     * 获取（刷新）授权公众号或小程序的接口调用凭据（令牌）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
     * @param string $authorizerAppid  授权方appid
     * @param string $refreshTokenValue 授权方的刷新令牌
     * @return mixed 授权数组
     */
    public function getAuthorizerToken($authorizerAppid, $refreshTokenValue)
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token={$accessToken}";        
        $post = $this->toJson([
            'component_appid'   => $this->config['appid'],
            'authorizer_appid'  => $authorizerAppid,
            'authorizer_refresh_token' => $refreshTokenValue,
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        //返回数据结构
        //{
        //    "authorizer_access_token": "aaUl5s6kAByLwgV0BhXNuIFFUqfrR8vTATsoSHukcIGqJgrc4KmMJ-JlKoC_-NKCLBvuU1cWPv4vDcLN8Z0pn5I45mpATruU0b51hzeT1f8", 
        //    "expires_in": 7200, 
        //    "authorizer_refresh_token": "BstnRqgTJBXb9N2aJq6L5hzfJwP406tpfahQeLNxX0w"
        //}
        return $wxdata;
    }

    /**
     * 获取授权方的帐号基本信息
     * @param string $authorizerAppid 授权公众号或小程序的appid
     * @return mixed
     */
    public function getAuthorizerInfo($authorizerAppid)
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token={$accessToken}";        
        $post = $this->toJson([
            'component_appid'   => $this->config['appid'],
            'authorizer_appid'  => $authorizerAppid,
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        //返回数据结构（小程序）
        //{
        //    "authorizer_info": {
        //        "nick_name": "微信SDK Demo Special", 
        //        "head_img": "http://wx.qlogo.cn/mmopen/GPy", 
        //        "service_type_info": { "id": 2 }, 
        //        "verify_type_info": { "id": 0 },
        //        "user_name":"gh_eb5e3a772040",
        //        "principal_name":"腾讯计算机系统有限公司",
        //        "business_info": {"open_store": 0, "open_scan": 0, "open_pay": 0, "open_card": 0, "open_shake": 0},
        //        "qrcode_url":"URL",
        //        "signature": "时间的水缓缓流去",
        //        "MiniProgramInfo": {
        //            "network": {
        //                "RequestDomain":["https://www.qq.com","https://www.qq.com"],
        //                "WsRequestDomain":["wss://www.qq.com","wss://www.qq.com"],
        //                "UploadDomain":["https://www.qq.com","https://www.qq.com"],
        //                "DownloadDomain":["https://www.qq.com","https://www.qq.com"],
        //            },
        //            "categories":[{"first":"资讯","second":"文娱"},{"first":"工具","second":"天气"}],
        //            "visit_status": 0,
        //        }
        //    },
        //    "authorization_info": {
        //        "appid": "wxf8b4f85f3a794e77", 
        //        "func_info": [
        //            { "funcscope_category": { "id": 17 } }, 
        //        ]
        //    }
        //}
        
        //返回数据结构（公众号）
        //{
        //    "authorizer_info": {
        //        "nick_name": "微信SDK Demo Special", 
        //        "head_img": "http://wx.qlogo.cn/mmopen/GPy", 
        //        "service_type_info": { "id": 2 }, 
        //        "verify_type_info": { "id": 0 },
        //        "user_name":"gh_eb5e3a772040",
        //        "principal_name":"腾讯计算机系统有限公司",
        //        "business_info": {"open_store": 0, "open_scan": 0, "open_pay": 0, "open_card": 0, "open_shake": 0},
        //        "alias":"paytest01"
        //        "qrcode_url":"URL",
        //    },     
        //    "authorization_info": {
        //        "appid": "wxf8b4f85f3a794e77", 
        //        "func_info": [
        //            { "funcscope_category": { "id": 1 } }, 
        //        ]
        //    }
        //}
        return $wxdata;
    }

    /**
     * 获取授权方的选项设置信息
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
     * @param string $authorizerAppid 授权公众号或小程序的appid
     * @param string $optionName 选项名称
     * @return boolean
     */
    public function getAuthorizerOption($authorizerAppid, $optionName)
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_option?component_access_token={$accessToken}";        
        $post = $this->toJson([
            'component_appid'   => $this->config['appid'],
            'authorizer_appid'  => $authorizerAppid,
            "option_name"       => $optionName
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        //返回数据结构
        //{
        //    "authorizer_appid":"wx7bc5ba58cabd00f4",
        //    "option_name":"voice_recognize", //选项名称
        //    "option_value":"1" //选项值
        //}
        return $wxdata;
    }
    
    /**
     * 设置授权方的选项信息
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=&lang=zh_CN
     * @param string $authorizerAppid 授权公众号或小程序的appid
     * @param string $optionName 选项名称
     * @param string $optionValue 设置的选项值
     * @return boolean
     */
    public function setAuthorizerOption($authorizerAppid, $optionName, $optionValue)
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/cgi-bin/component/api_set_authorizer_option?component_access_token={$accessToken}";        
        $post = $this->toJson([
            'component_appid'   => $this->config['appid'],
            'authorizer_appid'  => $authorizerAppid,
            "option_name"       => $optionName,
            "option_value"      => $optionValue
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }
    
    /**
     * 获取授权的页面url
     * @return string
     */
    public function getAuthUrl()
    {
        $preAuthCode = $this->getPreAuthCode();
        if ($preAuthCode === false) {
            return false;
        }
        //回调url
        $redirectUri = SITE_URL.'/admin/wx3rd/authorization';
        $url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage'
                . '?component_appid='.$this->config['appid']
                . '&pre_auth_code='.urlencode($preAuthCode)
                . '&redirect_uri='.urlencode($redirectUri);
        return $url;
    }
    
    /**
     * 获取推送解密的消息
     * @return mixed 消息数组
     */
    public function getDecryptPushMessage()
    {
        $content = file_get_contents('php://input');
        if (empty($content)) {
            $this->setError('推送消息为空！');
            return false;
        }
        
        $this->logDebugFile($content);
        
        $decryptMsg = $this->decryptPushMsg($content);
        
        $this->logDebugFile($decryptMsg);

        $message = XML::parse($decryptMsg);
        if (empty($message)) {
            $this->setError('推送消息内容为空！');
            return false;
        }
        $this->logDebugFile($message);
        
        return $message;
    }
    
    /**
     * 解密推送消息
     * @param string $encryptMsg
     */
    public function decryptPushMsg($encryptMsg)
    {
        vendor('wechat.msgencrypt.WXBizMsgCrypt');

        $xmlTree = new \DOMDocument();
        $xmlTree->loadXML($encryptMsg);
        $arrayEnc = $xmlTree->getElementsByTagName('Encrypt');
        $encrypt = $arrayEnc->item(0)->nodeValue;

        $format = "<xml><AppId><![CDATA[%s]]></AppId><Encrypt><![CDATA[%s]]></Encrypt></xml>";
        $fromXml = sprintf($format, $this->config['appid'], $encrypt);

        $msgSignature = input('msg_signature', '');
        $timeStamp = input('timestamp', '');
        $nonce = input('nonce', '');
        $this->logDebugFile(compact('msgSignature', 'timeStamp', 'nonce'));

        $wxBizMsgCrypt = new \WXBizMsgCrypt($this->config['verify_token'], $this->config['encoding_aes_key'], $this->config['appid']);
        $errCode = $wxBizMsgCrypt->decryptMsg($msgSignature, $timeStamp, $nonce, $fromXml, $msg);
        if ($errCode != 0) {
            $this->logDebugFile('解密错误码：'.$errCode);
            $this->setError('解密错误码：'.$errCode);
            return false;
        } 
        
        return $msg;
    }

    /**
     * 获取普通推送消息
     * @return array|bool|\SimpleXMLElement
     */
    public function getPushMessage()
    {
        $content = file_get_contents('php://input');
        if (empty($content)) {
            $this->setError('推送消息为空！');
            return false;
        }

        $this->logDebugFile($content);

        $message = \app\common\util\XML::parse($content);
        if (empty($message)) {
            $this->setError('推送消息内容为空！');
            return false;
        }
        $this->logDebugFile($message);

        return $message;
    }

    /**
     * 获取授权的用户列表
     * @param int $count 一次获取的数量，最大500
     * @param int $offset 获取数量的偏移值
     * @return bool|mixed|string
     */
    public function getAuthorizerList($count = 500, $offset = 0)
    {
        $accessToken = $this->getComponentAccessToken();
        if (!$accessToken) {
            return false;
        }

        $url ="https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_list?component_access_token={$accessToken}";
        $post = $this->toJson([
            'component_appid'   => $this->config['appid'],
            'offset'  => $offset,
            "count"   => $count
        ]);

        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }

        //返回数据结构
        //{
        //    "total_count":33,
        //    list:[
        //        {
        //          "authorizer_appid": "authorizer_appid_1",
        //          "refresh_token": "refresh_token_1",
        //          "auth_time": auth_time_1
        //        },
        //    ]
        //}
        return $wxdata;
    }

    /**
     * 处理授权消息事件
     */
    public function handleAuthEvent($event, $callback)
    {
        static $msg = null;
        if (!$msg) {
            $msg = $this->getDecryptPushMessage();
            if (!$msg) {
                exit($this->getError());
            }
        }

        // 先处理全局事件
        if ($event == self::AUTH_EVENT_ALL) {
            is_callable($callback) && $callback($msg);
            return;
        }

        static $eventParse = [
            self::AUTH_EVENT_VERIFY_TICKET => ['InfoType' => 'component_verify_ticket'],
            self::AUTH_EVENT_UNAUTHORIZED => ['InfoType' => 'unauthorized'],
            self::AUTH_EVENT_AUTHORIZED => ['InfoType' => 'authorized'],
            self::AUTH_EVENT_UPDATE_AUTHORIZED => ['InfoType' => 'updateauthorized'],
        ];

        $this->callEventHandle($eventParse, $event, $callback, $msg);
    }

    /**
     * 处理普通消息事件
     */
    public function handleCommonEvent($event, $callback)
    {
        static $msg = null;
        if (!$msg) {
            $msg = $this->getDecryptPushMessage();
            if (!$msg) {
                exit($this->getError());
            }
        }

        // 先处理全局事件
        if ($event == self::COMMON_EVENT_ALL) {
            is_callable($callback) && $callback($msg);
            return;
        }

        static $eventParse = [
            self::COMMON_EVENT_WEAPP_AUDIT_SUCCESS => ['MsgType' => 'event', 'Event' => 'weapp_audit_success'],
            self::COMMON_EVENT_WEAPP_AUDIT_FAIL => ['MsgType' => 'event', 'Event' => 'weapp_audit_fail'],
        ];

        $this->callEventHandle($eventParse, $event, $callback, $msg);
    }

    /**
     * 回调事件处理
     * @param $eventParse
     * @param $event
     * @param $callback
     * @param $msg
     */
    private function callEventHandle($eventParse, $event, $callback, $msg)
    {
        if (!isset($eventParse[$event])) {
            return;
        }

        $findEvent = true;
        foreach ($eventParse[$event] as $key => $word) {
            if ($msg[$key] !== $word) {
                $findEvent = false;
                break;
            }
        }
        if (!$findEvent) {
            return;
        }

        is_callable($callback) && $callback($msg);
    }
}