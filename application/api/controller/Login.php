<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/22
 * Time: 17:53
 */

namespace app\api\controller;

use think\Db;
use think\Loader;
use think\Request;
use think\Session;
use think\captcha\Captcha;
use app\common\util\jwt\JWT;

class Login extends \think\Controller
{



    /**
     * 微信登录
     */
    public function index () {
        $code = I('code');
        if(!$code){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'code不能为空','data'=>'']);
        }

        $appid = M('config')->where(['name'=>'appid'])->value('value');
        $appsecret = M('config')->where(['name'=>'appsecret'])->value('value');

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$appid.'&secret='.$appsecret.'&js_code='.$code.'&grant_type=authorization_code' ;
        $result = httpRequest($url, 'GET');
        $arr = json_decode($result, true);
        if(!isset($arr['openid'])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>$arr['errmsg'],'data'=>'']);
        }

        $openid = $arr['openid'];

        // 查询数据库，判断是否有此openid
        $data = Db::table('member')->where('openid',$openid)->find();
        if(!$data){
            Db::table('member')->insert(['openid'=>$openid]);
            $data = Db::table('member')->where('openid',$openid)->find();

            $data['token'] = $this->create_token($data['id']);

            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
        }else{

            $data['token'] = $this->create_token($data['id']);

            $this->ajaxReturn( ['status'=>1,'msg'=>'获取用户信息成功','data'=>$data]);

        }

    }


    /**
     * 登录接口
     */
    public function login()
    {

        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
        header('Content-Type:application/json; charset=utf-8');

        $mobile    = input('mobile');
        $password1 = input('password');
        $password  = md5('TPSHOP'.$password1);

        $data = Db::name("users")->where('mobile',$mobile)
            ->field('password,user_id')
            ->find();

        if(!$data){
            exit(json_encode(['status' => -1 , 'msg'=>'手机不存在或错误','data'=>null]));
        }
        if ($password != $data['password']) {
            exit(json_encode(['status' => -2 , 'msg'=>'登录密码错误','data'=>null]));
        }
        unset($data['password']);
        //重写
        $data['token'] = $this->create_token($data['user_id']);
        
        exit(json_encode(['status' => 0 , 'msg'=>'登录成功','data'=>$data],JSON_UNESCAPED_UNICODE));

    }

    /**
     * 生成token
     */
    private function create_token($user_id){
        $time = time();
        $payload = array(
            "iss"=> "DC",
            "iat"=> $time ,
            "exp"=> $time + 36000 ,
            "user_id"=> $user_id
        );
        $key = 'zhelishimiyao';
        $token = JWT::encode($payload, $key, $alg = 'HS256', $keyId = null, $head = null);
        return $token;
    }




}