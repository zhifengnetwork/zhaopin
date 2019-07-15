<?php


namespace app\common\logic;

use think\Cache;
use think\Db;
use think\Config;
use think\Session;

\think\Loader::import('controller/Jump', TRAIT_PATH, EXT);

class Saas
{
    use \traits\controller\Jump;

    /**
     * @var self
     */
    static private $instance = null;

    private $isSaas = false;
    private $isBaseUser = false;
    private $app = []; //本应用配置
    private $saas = []; //saas总配置
    private $loginUrl = ''; //登录链接

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->isSaas = (IS_SAAS === 1);
        $this->isBaseUser = (SAAS_BASE_USER === 1);
        $this->app = $GLOBALS['SAAS_CONFIG'];
        $this->saas = $GLOBALS['SAAS'];
        $this->loginUrl = SITE_URL.'/admin/admin/login';
    }

    public function isSaas()
    {
        return $this->isSaas;
    }

    public function isNormalUser()
    {
        return $this->isSaas && !$this->isBaseUser;
    }

    public function isBaseUser()
    {
        return $this->isBaseUser;
    }

    public function getRoleRights($actList)
    {
        $module = request()->module();
        $limitRights = $this->app['right'][$module];
        if ($actList === 'all') {
            $roleRights = implode(',', $limitRights);;
        } else {
            $roleRights = explode(',', $actList);
            $roleRights = array_intersect($roleRights, $limitRights);
            $roleRights = implode(',', $roleRights);
        }
        return $roleRights;
    }

    public function checkApiRight($terminal)
    {
        if (!$this->isSaas()) {
            return;
        }

        $return = ['status' => 1, 'msg' => '检查成功'];
        switch ($terminal) {
            case 'miniapp':
                if (empty($this->app['miniapp_enable'])) {
                    $return = ['status' => -1, 'msg' => '小程序版已过期'];
                }
                break;
            case 'android':
                if (empty($this->app['android_enable'])) {
                    $return = ['status' => -1, 'msg' => '安卓版已过期'];
                }
                break;
            case 'ios':
                if (empty($this->app['android_enable'])) {
                    $return = ['status' => -1, 'msg' => '苹果版已过期'];
                }
                break;
            default:
                $return = ['status' => -1, 'msg' => '接口没访问权限'];
        }

        if ($return['status'] != 1) {
            ajaxReturn($return);
        }
    }

    /**
     * 检查是否单点登录
     */
    public function checkSso()
    {
        if (!$this->isSaas()) {
            return;
        }

        //过滤不需要登陆的行为
        $action = request()->action();
        if (!in_array($action, ['login', 'vertify', 'forget_pwd'])) {
            if (!session('admin_id')) {
                $this->redirectSso();
            }
        } elseif ($action == 'login' && request()->isGet()) {
            $isLogin = input('is_login', 0);
            if ($isLogin != 1) {
                $msg = input('err_msg');
                $msg && $this->error($msg, $this->loginUrl);
                if (!session('had_redirect_sso')) {
                    session('had_redirect_sso', 1);
                    $this->redirectSso();
                } else {
                    session('had_redirect_sso', 0);
                }
            } else {
                session('had_redirect_sso', 0);
                if (!$ssoToken = input('sso_token', '')) {
                    $this->error('平台已退出登录', $this->loginUrl);
                }

                $this->verifySsoToken($ssoToken);

                $key = $this->getSsoTokenKey();
                Cache::set($key, ['session_id' => session_id(), 'sso_token' => $ssoToken], 0);

                $admin = Db::name('admin')->alias('a')->join('__ADMIN_ROLE__ ar', 'a.role_id=ar.role_id')->where('admin_id', 1)->find();
                (new AdminLogic)->handleLogin($admin, $admin['act_list']);

                $this->redirect(url('admin/index/index'));
            }
        }
    }

    private function verifySsoToken($ssoToken)
    {
        $params = http_build_query([
            'service_domain' => $this->app['domain'],
            'app_domain' => $this->saas['main_domain'],
            'sso_token' => $ssoToken,
        ]);
        $verifyUrl = 'http://'.$this->saas['saas_domain'].'/client/sso/verify_token?'.$params;
        $result = httpRequest($verifyUrl);
        if (!$result = json_decode($result, true)) {
            $this->error('请求验证sso令牌失败', $this->loginUrl);
        }
        if ($result['status'] != 1) {
            $this->error($result['msg'], $this->loginUrl);
        }
    }

    private function redirectSso()
    {
        $params = http_build_query([
            'service_domain' => $this->app['domain'],
            'app_domain' => $this->saas['main_domain'],
            'redirect' => urlencode($this->loginUrl),
        ]);
        $this->redirect('http://'.$this->saas['saas_domain'].'/client/sso/check_login?'.$params);
    }

    /**
     * admin单点登录
     */
    public function ssoAdmin($username, $password)
    {
        if (!$this->isSaas()) {
            return;
        }

        $condition['a.admin_id'] = 1;
        $condition['a.user_name'] = $username;
        $admin = Db::name('admin')->alias('a')->join('__ADMIN_ROLE__ ar', 'a.role_id=ar.role_id')->where($condition)->find();
        if (!$admin) {
            return;
        }

        $params = http_build_query([
            'service_domain' => $this->app['domain'],
            'app_domain' => $this->saas['main_domain'],
            'redirect' => urlencode($this->loginUrl),
            'password' => encrypt($password),
        ]);
        ajaxReturn(['status' => 1, 'url' => 'http://'.$this->saas['saas_domain'].'/client/sso/login?'.$params]);
    }

    private function getSsoTokenKey()
    {
        return 'sso_token';//一个应用子站只有一个ssoToken,因为只有一个admin
    }

    public function ssoLogout($ssoToken)
    {
        if (!$ssoToken) {
            return ['status' => -1, 'msg' => '令牌为空'];
        }

        $key = $this->getSsoTokenKey();
        $config = Cache::get($key);
        if (!$config) {
            return ['status' => 1, 'msg' => '子站没有令牌'];
        }
        if ($config['sso_token'] !== $ssoToken) {
            return ['status' => -1, 'msg' => '令牌不正确'];
        }

        if ($config['session_id']) {
            session_id($config['session_id']);
            Session::start();
            Session::clear();
            session_unset();
            session_destroy();
        }

        Cache::rm($key);

        return ['status' => 1, 'msg' => '登出成功'];
    }

    public function handleLogout($adminId)
    {
        if (!$this->isSaas() || $adminId != 1) {
            return;
        }

        $key = $this->getSsoTokenKey();
        if (!$config = Cache::get($key)) {
            return;
        }
        Cache::rm($key);

        if ($config['sso_token']) {
            $logoutUrl = 'http://'.$this->saas['saas_domain'].'/client/sso/logout?sso_token='.$config['sso_token'];
            $result = httpRequest($logoutUrl);
            if (!$result = json_decode($result, true)) {
                $this->error('saas平台退出失败');
            }
            if ($result['status'] != 1) {
                $this->error($result['msg']);
            }
        }
    }

    public function initSaas()
    {
        if (!$this->isSaas()) {
            return;
        }

        $database = Config::get('database');
        $saas = $this->app;
        Config::set('database', array_merge($database, $saas['database'] ?: []));
        Config::set('session.prefix', 'tp_'.$saas['domain']);
        Config::set('cookie.prefix', 'tp_'.$saas['domain']);

        $this->checkPrivilege();
    }

    private function checkPrivilege()
    {
        if (!$this->isNormalUser()) {
            return;
        }

        $module     = request()->module();
        $controller = request()->controller();
        $action     = request()->action();

        if ($controller == 'Sso') {
            return;
        }

        $actList = $this->app['right'][$module];
        $right = Db::name('system_menu')->whereIn("id", $actList)->getField('right', true);
        $roleRight = '';
        foreach ($right as $val) {
            $roleRight .= $val.',';
        }
        $roleRight = explode(',', $roleRight);
//        if (!in_array($controller . '@' . $action, $roleRight)) {
//            if (request()->isAjax() || input('is_ajax') || input('is_json') || strpos($action, 'ajax') !== false) {
//                ajaxReturn(['status' => -1, 'msg' => '您暂没有权限操作', 'result' => '']);
//            } else {
//                $this->error('您暂没有权限操作', null, '', 1000);
//            }
//        }
    }
}