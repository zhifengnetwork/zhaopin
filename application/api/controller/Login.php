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
    public function index () {
        redirect('login/login')->send();
        exit;
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





//    public function login () {
//        if (Request::instance()->isPost()) {
//            $username = input('username');
//            $password = input('password');
//            // 实例化验证器
//            $validate = Loader::validate('Login');
//            // 验证数据
//            $data = ['username' => $username, 'password' => $password];
//            // 验证
//            $code = input('captcha');
//            $str = session('captcha_id');
//            $captcha = new \think\captcha\Captcha();
//            if (!$captcha->check($code,$str)){
//                return json(['code'=>0,'msg'=>'验证码错误']);
//            }
//            if (!$validate->check($data)) {
//                return $this->error($validate->getError());
//            }
//            $where['username'] = $username;
//            $where['status']   = 1;
//            $user_info = Db::table('mg_user')->where($where)->find();
//            if ($user_info && $user_info['password'] === minishop_md5($password, $user_info['salt'])) {
//                $session['uid']     = $user_info['mgid'];
//                $session['user_name'] = $user_info['username'];
//                // 记录用户登录信息
//                Session::set('admin_user_auth', $session);
//                return json(['code'=>1,'msg'=>'登录成功']);
//            }
//            return json(['code'=>0,'msg'=>'密码错误！']);
//        }
//    }



//    /*
//     *  获取验证码
//      */
//    public function loginCaptcha () {
//        $str  = time().uniqid();
//        Session::set('captcha_id', $str);
//        $captcha = new Captcha();
//        return $captcha->entry($str);
//    }
//
//    /*
//     * 退出登录
//     */
//    public function login_out()
//    {
//        session('admin_user_auth', null);
//        session('ALL_MENU_LIST', null);
//        return json(['code'=>1,'msg'=>'请登录','data'=>'']);
//    }
}