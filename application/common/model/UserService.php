<?php
namespace app\common\model;

use \think\Db;
use \think\Config;
use \think\Cache;
use alipay\AopClient;
use alipay\AlipaySystemOauthTokenRequest;
use alipay\SignData;
/**
 * 用户服务
 */
class UserService
{
    public static function init_user($openid,$type)
    {
        $userinfo = Db::table('user')->where(['wx_openid'=>$openid])->find();
        if (!$userinfo && $openid){
            $userinfo = ['wx_openid' => $openid, 'create_time' => time(),'type' =>$type];
            $uid = Db::table('user')->insertGetId($userinfo);
            $userinfo['uid'] = $uid;
        }
        return $userinfo;
    }

    public static function init_userinfo($wx_user)
    {
        $uid    = Db::table('user')->where(['wx_openid'=>$wx_user['openid']])->value('uid');
        $info   = Db::table('user_info')->where(['uid'=>$uid])->find();
        $data   = [
          'name' => $wx_user['nickname'],
          'sex'  => $wx_user['sex'],
          'city' => $wx_user['city'],
          'head_img' => $wx_user['headimgurl'],
          'province' => $wx_user['province'],
        ];
        if ($info){
            Db::table('user_info')->where(['uid'=>$uid])->update($data);
        }else{
            $data['uid'] = $uid;
            Db::table('user_info')->insert($data);
        }
    }
    /**
     * 小程序初始化用户openid
     */
    public static function wxmini_openid($code='')
    {
        //TODO 提取配置appid secret 配置
        $response = file_get_contents('https://api.weixin.qq.com/sns/jscode2session?appid=wx41f75da55f643074&secret=076309435b2d2a6cfb988ced51337023&js_code='.$code.'&grant_type=authorization_code');
        $response = json_decode($response, true);
        if (isset($response['errcode'])) {
            return false;
        }
        $openid = $response['openid'];
        $userinfo = self::init_user($openid,1);
        return $userinfo;
    }

    public static function alipay_auth()
    {
        $code = input('auth_code');
        $config = config('zfb_config'); // 获取支付宝相关配置
        //vendor('alipaysdk.AopSdk');
        //file_put_contents('999999888.php', $code);
        $c  = new  AopClient;
       
        $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $c->appId = $config['appid'];
        $c->rsaPrivateKey = $config['private_key'];
        $c->format = "json";
        $c->charset= "GBK";
        $c->signType= "RSA2";
        $c->apiVersion = '1.0';
        $c->alipayrsaPublicKey = $config['key'];
        //获取access_token
        $request = new AlipaySystemOauthTokenRequest();
        $request->setGrantType("authorization_code");
        $request->setCode($code);//这里传入 code
        $result = $c->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $user_id = $result->$responseNode->user_id;
        return $user_id;
    }
}
