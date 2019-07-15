<?php


namespace app\common\logic\saas\wechat;

use app\common\model\saas\Miniapp;

/**
 * 微信小程序第三方平台操作类
 */
class MiniApp3rd extends WxCommon
{
    private $config = []; //小程序配置

    /**
     * @param array|string $config 授权用户的配置，或者appid
     */
    public function __construct($config)
    {
        if (is_string($config)) {
            $config = Miniapp::get(['appid' => $config]);
        }
        if ($config instanceof Miniapp) {
            $config = $config->toArray();
        }
        $this->config = $config ?: [];
    }
    
    /**
     * 获取小程序session信息
     * @param string $code 登录码
     * @return array|bool
     */
    public function getSessionInfo($code)
    {
        $appId = $this->config['appid'];
        if (!$appId) {
            $this->setError('授权用户不存在');
            return false;
        }
        
        $wx3rd = Wx3rdPlatform::getInstance();
        $wx3rdCfg = $wx3rd->getConfig();
        if (!$wx3rdCfg) {
            $this->setError('第三方平台信息未完善');
            return false;
        }

        $url = 'https://api.weixin.qq.com/sns/component/jscode2session';
        $fields = [
            'appid' => $appId,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
            'component_appid' => $wx3rdCfg['appid'],
            'component_access_token' => $wx3rdCfg['access_token']
        ];

        $wxdata = $this->requestAndCheck($url, 'GET', $fields);
        if ($wxdata === false) {
            return false;
        }
        return $wxdata;
    }
    
    /**
     * 获取授权者的authorizer_access_token
     * @return boolean
     */
    public function getAccessToken()
    {
        $config = $this->config;
        if (empty($config) || !$config['appid'] || !$config['access_token'] || !$config['refresh_token']) {
            $this->setError("授权信息不全，请先授权");
            return false;
        }

        //判断是否过了缓存期
        if ($config['access_token_expires'] > time()) {
           return $config['access_token'];
        }
        
        $wx3rd = Wx3rdPlatform::getInstance();
        $return = $wx3rd->getAuthorizerToken($config['appid'], $config['refresh_token']);
        if ($return === false) {
            $this->setError($wx3rd->getError());
            $this->config['access_token_expires'] = 0;
            Miniapp::update(['access_token_expires' => 0], ['user_id' => $config['user_id']]);
            return false;
        }
        $data = [
            'access_token'          => $return['authorizer_access_token'],
            'access_token_expires'  => $return['expires_in'] + time() - 200, //提前200s失效
            'refresh_token'         => $return['authorizer_refresh_token'],
        ];
        Miniapp::update($data, ['user_id' => $config['user_id']]);
        $this->config = array_merge($config, $data);
        
        return $return['authorizer_access_token'];
    }

    /**
     * 修改服务器地址
     * 详看：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489138143_WPbOO&token=&lang=zh_CN
     * @param string $action add添加, delete删除, set覆盖
     * @param array $domains
     *              requestDomain request合法域名
     *              wsrequestDomain socket合法域名
     *              uploadDomain uploadFile合法域名
     *              downloadDomain downloadFile合法域名
     * @return boolean
     */
    public function modifyDomain($action, $domains)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/modify_domain?access_token={$accessToken}";        
        $post = $this->toJson([
            'action'            => $action,
            'requestdomain'     => $domains['requestdomain'],
            'wsrequestdomain'   => $domains['wsrequestdomain'],
            'uploaddomain'      => $domains['uploaddomain'],
            'downloaddomain'    => $domains['downloaddomain'],
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }

    /**
     * 设置业务域名
     * @return bool
     */
    public function setWebViewDomain($action, $webviewdomain)
    {
        if (!$accessToken = $this->getAccessToken()) {
            return false;
        }
        $post = $this->toJson([
            'action'            => $action,
            'webviewdomain'     => $webviewdomain,
        ]);
//        $post = '{}'; //官方要求空的数据包
        $url ="https://api.weixin.qq.com/wxa/setwebviewdomain?access_token={$accessToken}";

        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }
    
    /**
     * 获取服务器地址
     * 详看：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489138143_WPbOO&token=&lang=zh_CN
     * @return mixed
     */
    public function getDomain()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/modify_domain?access_token={$accessToken}";        
        $post = $this->toJson([
            'action' => 'get',
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        return [
            'requestdomain'   => $wxdata['requestdomain'],
            'wsrequestdomain' => $wxdata['wsrequestdomain'],
            'uploaddomain'    => $wxdata['uploaddomain'],
            'downloaddomain'  => $wxdata['downloaddomain'],
        ];
    }
    
    /**
     * 绑定微信用户为小程序体验者
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140588_nVUgx&token=&lang=zh_CN
     * @param string $wechatId 微信号
     * @return boolean
     */
    public function bindTester($wechatId)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/bind_tester?access_token={$accessToken}";        
        $post = $this->toJson([
            'wechatid' => $wechatId,
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }

    /**
     * 解绑体验者
     * @param $wechatId
     * @return bool
     */
    public function unbindTester($wechatId)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $url ="https://api.weixin.qq.com/wxa/unbind_tester?access_token={$accessToken}";
        $post = $this->toJson([
            'wechatid' => $wechatId,
        ]);

        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }
    
    /**
     * 为授权的小程序帐号上传小程序代码
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @param string $templateId 代码库中的代码模版ID
     * @param string|array $extCfg 第三方自定义的配置json
     * @param string $version 代码版本号，开发者可自定义
     * @param string $description 代码描述，开发者可自定义
     * @return boolean
     */
    public function commit($templateId, $extCfg, $version, $description)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        if (!is_string($extCfg)) {
            $extCfg = $this->toJson($extCfg);
        }
        
        $url ="https://api.weixin.qq.com/wxa/commit?access_token={$accessToken}";        
        $post = $this->toJson([
            'template_id' => $templateId,
            'ext_json' => $extCfg, //需为string类型
            'user_version' => $version, //代码版本号
            'user_desc' => $description,
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }
    
    /**
     * 获取体验小程序的体验二维码链接
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @return string
     */
    public function getTestQrcode()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/get_qrcode?access_token={$accessToken}";
        $wxdata = $this->requestAndCheck($url, 'GET', [], false);

        return $wxdata;
    }
    
    /**
     * 获取授权小程序帐号的可选类目
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @return mixed
     */
    public function getCategory()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/get_category?access_token={$accessToken}";        
        
        $wxdata = $this->requestAndCheck($url);
        if ($wxdata === false) {
            return false;
        }
 
//返回格式：如下：
//      [{
//          "first_class":"教育", //一级类目名称
//			"second_class":"学历教育",
//			"third_class":"高等"
//          "first_id":3, //一级类目的ID编号
//          "second_id":4,
//          "third_id":5,
//		}]
        return $wxdata['category_list'];
    }
    
    /**
     * 获取小程序的第三方提交代码的页面配置（仅供第三方开发者代小程序调用）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @return mixed
     */
    public function getPage()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/get_page?access_token={$accessToken}";        
        
        $wxdata = $this->requestAndCheck($url);
        if ($wxdata === false) {
            return false;
        }
        
        return $wxdata['page_list'];
    }
    
    /**
     * 将第三方提交的代码包提交审核（仅供第三方开发者代小程序调用）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @param array $itemList
     * 示例：[{
                "address":"page/logs/logs",//小程序的页面，可通过“获取小程序的第三方提交代码的页面配置”接口获得
                "tag":"学习 工作",          //小程序的标签，多个标签用空格分隔，标签不能多于10个，标签长度不超过20
                "first_class": "教育",      //一级类目名称，可通过“获取授权小程序帐号的可选类目”接口获得
                "second_class": "学历教育", //二级类目(同上)  
                "third_class": "高等",      //三级类目(同上)
                "first_id":3,               //一级类目的ID，可通过“获取授权小程序帐号的可选类目”接口获得
                "second_id":4,              //二级类目的ID(同上)
                "third_id":5,               //三级类目的ID(同上)
                "title": "日志"             //小程序页面的标题,标题长度不超过32
            }]
     * @return mixed 审核编号
     */
    public function submitAudit($itemList)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/submit_audit?access_token={$accessToken}";
        $post = $this->toJson([
            'item_list' => $itemList
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        return $wxdata['auditid'];
    }

    /**
     * 查询某个指定版本的审核状态（仅供第三方代小程序调用）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @param string $auditId 提交审核时获得的审核id
     * @return mixed 审核结果数组
     */
    public function getAuditStatus($auditId)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/get_auditstatus?access_token={$accessToken}";        
        $post = $this->toJson([
            'auditid' => $auditId
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        if ($wxdata === false) {
            return false;
        }
        
        return [
            'status' => $wxdata['status'],  //0为审核成功，1为审核失败，2为审核中
            'reason' => isset($wxdata['reason']) ? $wxdata['reason'] : '' //当status=1，审核被拒绝时，返回的拒绝原因
        ];
    }
    
    /**
     * 查询最新一次提交的审核状态（仅供第三方代小程序调用）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @return mixed 审核结果数组
     */
    public function getLatestAuditStatus()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/get_latest_auditstatus?access_token={$accessToken}";
        $wxdata = $this->requestAndCheck($url);
        if ($wxdata === false) {
            return false;
        }
        
        return [
            'auditid' => $wxdata['auditid'], //最新的审核ID
            'status' => $wxdata['status'],  //0为审核成功，1为审核失败，2为审核中
            'reason' => isset($wxdata['reason']) ? $wxdata['reason'] : '' //当status=1，审核被拒绝时，返回的拒绝原因
        ];
    }
    
    /**
     * 发布已通过审核的小程序（仅供第三方代小程序调用）
     * 详见：https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489140610_Uavc4&token=&lang=zh_CN
     * @return boolean
     */
    public function release()
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        
        $url ="https://api.weixin.qq.com/wxa/release?access_token={$accessToken}";
        $post = '{}'; //官方要求空的数据包

        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }
    
    /**
     * 修改小程序线上代码的可见状态（仅供第三方代小程序调用）
     * @param string $action 设置可访问状态，发布后默认可访问，1可见 0不可见
     * @return boolean
     */
    public function changeVisitStatus($action)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $url ="https://api.weixin.qq.com/wxa/change_visitstatus?access_token={$accessToken}";        
        $post = $this->toJson([
            'action' => $action ? 'open' : 'close'
        ]);
        
        $wxdata = $this->requestAndCheck($url, 'POST', $post);
        return $wxdata !== false;
    }

    /**
     * 获取授权用户的详细信息
     * @return boolean | array
     */
    public function getAuthUserInfo()
    {
        $appId = $this->config['appid'];
        if (!$appId) {
            $this->setError('授权用户不存在');
            return false;
        }

        $wx3rd = Wx3rdPlatform::getInstance();
        return $wx3rd->getAuthorizerInfo($appId);
    }
}