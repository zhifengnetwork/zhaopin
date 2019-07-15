<?php


namespace app\common\logic;

use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 门店管理员类
 */
class Shopper extends Model
{
    private $shopper_name;
    private $shopper;
    private $shop;

    public function setShopperName($shopper_name)
    {
        $this->shopper_name = $shopper_name;
    }

    public function login($password)
    {
        $Shopper = new \app\common\model\Shopper();
        $this->shopper = $Shopper->where(['shopper_name' => $this->shopper_name])->find();
        if(empty($this->shopper)){
            throw new TpshopException('门店登录',0,['status' => 0, 'msg' => '门店账号不存在']);
        }
        $this->shop = $this->shopper->shop;
        if($this->shop['deleted'] == 1){
            throw new TpshopException('门店登录',0,['status' => 0, 'msg' => '门店已经被删除']);
        }
        if($this->shop['shop_status'] == 0){
            throw new TpshopException('门店登录',0,['status' => 0, 'msg' => '门店已关闭，请联系平台客服']);
        }
        $user = $this->shopper->users;
        if(empty($user)){
            throw new TpshopException('门店登录',0,['status' => 0, 'msg' => '门店没有绑定前台会员']);
        }
        if($user['password'] != $password){
            throw new TpshopException('门店登录',0,['status' => 0, 'msg' => '密码错误']);
        }
        session('shopper', $this->shopper->toArray());
        session('shopper_id', $this->shopper['shopper_id']);
        session('shop_id', $this->shopper['shop_id']);
        $this->shopper->last_login_time = time();
        $this->shopper->save();
        $this->log("门店管理中心登录");
    }

    /**
     * 管理员操作记录
     * @param $content|记录信息
     */
    public function log($content)
    {
        $log['log_time'] = time();
        $log['log_shopper_id'] = $this->shopper['shopper_id'];
        $log['log_shopper_name'] = $this->shopper['shopper_name'];
        $log['log_content'] = $content;
        $log['log_shopper_ip'] = request()->ip();
        $log['log_shop_id'] = $this->shopper['shop_id'];
        $log['log_url'] = request()->action();
        Db::name('shopper_log')->add($log);
    }
}