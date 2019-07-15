<?php


namespace app\common\logic;

use think\Db;
use think\Session;

class AdminLogic
{
    public function login($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['status' => 0, 'msg' => '请填写账号密码'];
        }

        Saas::instance()->ssoAdmin($username, $password);

        $condition['a.user_name'] = $username;
        $condition['a.password'] = encrypt($password);
        $admin = Db::name('admin')->alias('a')->join('__ADMIN_ROLE__ ar', 'a.role_id=ar.role_id')->where($condition)->find();
        if (!$admin) {
            return ['status' => 0, 'msg' => '账号密码不正确'];
        }

        $this->handleLogin($admin, $admin['act_list']);

        $url = session('from_url') ? session('from_url') : U('Admin/Index/index');
        return ['status' => 1, 'url' => $url];
    }

    public function handleLogin($admin, $actList)
    {
        Db::name('admin')->where('admin_id', $admin['admin_id'])->save([
            'last_login' => time(),
            'last_ip' => request()->ip()
        ]);

        $this->sessionRoleRights($admin, $actList);

        session('admin_id', $admin['admin_id']);
        session('last_login_time', $admin['last_login']);
        session('last_login_ip', $admin['last_ip']);

        adminLog('后台登录');
    }

    public function sessionRoleRights($admin, $actList)
    {
        if (Saas::instance()->isNormalUser()) {
            $roleRights = Saas::instance()->getRoleRights($actList);
        } else {
            $roleRights = $actList;
        }

        session('act_list', $roleRights);
    }

    public function logout($adminId)
    {
        session_unset();
        session_destroy();
        Session::clear();

        Saas::instance()->handleLogout($adminId);
    }
}






