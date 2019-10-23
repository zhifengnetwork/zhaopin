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
use \think\Config;

class Login extends ApiBase
{



    /**
     * 微信登录
     */
    public function index () {
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
            Db::table('member')->insert(['openid'=>$openid,'regtype'=>4]);
            $data = Db::table('member')->where('openid',$openid)->find();
            $data['token'] = $this->create_token($data['id']);
            $this->ajaxReturn(['status' => 4 , 'msg'=>'获取成功','data'=>$data]);//微信第一次登陆跳转绑定手机号
        }else{
            $data['token'] = $this->create_token($data['id']);
            if($data['regtype']!=4){
                if($data['regtype']==1||$data['regtype']==2){
                    $company=Db::name('company')->where(['user_id'=>$data['id']])->find();
                    if(!$company){
                        $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
                    }elseif($company['status']==-1){
                        Db::name('company')->where(['user_id'=>$data['id']])->delete();
                        $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
                    }
                }elseif ($data['regtype']==3){
                    $person=Db::name('person')->where(['user_id'=>$data['id']])->find();
                    if(!$person){
                        $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
                    }elseif($person['status']==-1){
                        Db::name('person')->where(['user_id'=>$data['id']])->delete();
                        $this->ajaxReturn(['status' => 3, 'msg' => '继续填写！', 'data' => $data]);
                    }
                }
                $this->ajaxReturn( ['status'=>1,'msg'=>'获取用户信息成功','data'=>$data]);
            }else{
                $this->ajaxReturn(['status' => 4 , 'msg'=>'获取成功','data'=>$data]);
            }
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

        $wxConfig = Config::get('pay_weixin');

        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$wxConfig['app_id'].'&secret='.$wxConfig['app_secret'].'&js_code='.$code.'&grant_type=authorization_code' ;
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
                $this->ajaxReturn(['status' => 1 , 'msg'=>'绑定成功','data'=>[$openid]]);
            }else{
                $this->ajaxReturn(['status' => 1 , 'msg'=>'绑定失败','data'=>[$openid]]);
            }
        }else{

            if($user_id==$data['id']){
                $this->ajaxReturn( ['status'=>-2,'msg'=>'您已绑定该微信，请勿重新绑定','data'=>[$openid]]);
            }else{
                $this->ajaxReturn( ['status'=>-2,'msg'=>'该微信已绑定其他账户','data'=>[$openid]]);
            }


        }

    }






}