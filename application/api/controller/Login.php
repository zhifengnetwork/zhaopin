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

class Login extends ApiBase
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
     * 微信登录
     */
    public function bind_weixin () {
        $user_id=$this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $code = input('code');
        if(!$code){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'code不能为空','data'=>'']);
        }

        $appid = Db::name('config')->where(['name'=>'appid'])->value('value');
        $appsecret = Db::name('config')->where(['name'=>'appsecret'])->value('value');

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
            $res=Db::name('member')->where(['id'=>$user_id])->update(['openid'=>$openid]);
            if($res){
                $this->ajaxReturn(['status' => 1 , 'msg'=>'绑定成功','data'=>[]]);
            }else{
                $this->ajaxReturn(['status' => 1 , 'msg'=>'绑定失败','data'=>[]]);
            }
        }else{

//            $data['token'] = $this->create_token($data['id']);
            if($user_id==$data['id']){
                $this->ajaxReturn( ['status'=>-2,'msg'=>'您已绑定该微信，请勿重新绑定','data'=>[]]);
            }else{
                $this->ajaxReturn( ['status'=>-2,'msg'=>'该微信已绑定其他账户','data'=>[]]);
            }


        }

    }






}