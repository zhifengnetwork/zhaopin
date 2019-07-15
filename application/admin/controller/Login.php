<?php
namespace app\admin\controller;

use app\common\model\Config;
use think\Db;
use think\Loader;
use think\Request;
use think\Session;

/*
 * 后台管理控制器
 */
class Login extends \think\Controller
{
    /**
     * 登录
     */
    public function index()
    {
        if (Request::instance()->isPost()) {
            $username = input('post.username');
            $password = input('post.password');

            // 实例化验证器
            $validate = Loader::validate('Login');
            // 验证数据
            $data = ['username' => $username, 'password' => $password, 'captcha' => input('captcha')];
            // 验证
            if (!$validate->check($data)) {
                return $this->error($validate->getError());
            }

            $where['username'] = $username;
            $where['status']   = 1;

            $user_info = Db::table('mg_user')->where($where)->find();
//            $password='admin888';
////            $this->error('密码错误！'.$user_info['password']);
//            $this->error('密码错误！'.minishop_md5($password, $user_info['salt']));
//            exit;
            if ($user_info && $user_info['password'] === minishop_md5($password, $user_info['salt'])) {
                $session['mgid']     = $user_info['mgid'];
                $session['username'] = $user_info['username'];
                // 记录用户登录信息
                Session::set('admin_user_auth', $session);
                define('UID', $user_info['mgid']);
                $this->success('登陆成功！', url('index/index'));
            }
            $this->error('密码错误！');

        } else {
            // 登入标题
            config(Config::where('name', 'login_title')->column('value', 'name'));
            return $this->fetch();
        }
    }

    /*
     * 退出登录
     */
    public function login_out()
    {
        session('admin_user_auth', null);
        session('ALL_MENU_LIST', null);
        $this->redirect('login/index');
    }
}
