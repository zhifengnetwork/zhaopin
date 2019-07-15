<?php


namespace app\common\logic;


class ModuleLogic
{
    /**
     * 所有模块
     * @var array
     */
    public $modules = [];

    /**
     * 可见模块
     * @var array
     */
    public $showModules = [];

    public function getModules($onlyShow = true)
    {
        if ($this->modules) {
            return $onlyShow ? $this->showModules : $this->modules;
        }

        $isShow = Saas::instance()->isBaseUser() ? 1 : 0;
        $modules = [
            [
                'name'  => 'admin', 'title' => '平台后台', 'show' => 1,
                'privilege' => [
                    'system'=>'系统设置','content'=>'内容管理','goods'=>'商品中心','member'=>'会员中心','finance'=>'财务管理',
                    'order'=>'订单中心','marketing'=>'营销推广','tools'=>'插件工具','count'=>'统计报表','distribut'=>'分销中心','weixin'=>'微信管理'
                ],
            ],
            [
                'name'  => 'home', 'title' => 'PC端', 'show' => $isShow,
                'privilege' => [
                    'buy' => '购物流程', 'user' => '用户中心', 'article' => '文章功能', 'activity' => '活动优惠',
                    'virtual' => '虚拟商品', 'wechat' => '微信功能'
                ],
            ],
            [
                'name'  => 'mobile', 'title' => '手机端','show' => $isShow,
                'privilege' => [
                    'buy' => '购物流程', 'user' => '用户中心', 'article' => '文章功能', 'activity' => '活动优惠', 'distribut' => '分销功能',
                    'virtual' => '虚拟商品'
                ],
            ],
            [
                'name'  => 'api', 'title' => 'api接口', 'show' => $isShow,
                'privilege' => [
                    'buy' => '购物流程', 'user' => '用户中心', 'article' => '文章功能', 'activity' => '活动优惠', 'distribut' => '分销功能',
                    'virtual' => '虚拟商品', 'wechat' => '微信功能', 'message' => '消息推送', 'supplier' => '供应商', 'app' => '应用管理'
                ],
            ],
        ];

        $this->modules = $modules;
        foreach ($modules as $key => $module) {
            if (!$module['show']) {
                unset($modules[$key]);
            }
        }
        $this->showModules = $modules;

        return $onlyShow ? $this->showModules : $this->modules;
    }

    public function getModule($moduleIdx, $onlyShow = true)
    {
        if (!self::isModuleExist($moduleIdx, $onlyShow)) {
            return [];
        }

        $modules = $this->getModules($onlyShow);
        return $modules[$moduleIdx];
    }

    public function isModuleExist($moduleIdx, $onlyShow = true)
    {
        return key_exists($moduleIdx, $this->getModules($onlyShow));
    }

    public function getPrivilege($moduleIdx, $onlyShow = true)
    {
        $modules = $this->getModules($onlyShow);
        return $modules[$moduleIdx]['privilege'];
    }
}