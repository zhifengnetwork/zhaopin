<?php
namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class Member extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    /***
     * 充值积分and余额
     */
    public static function setBalance($uid = '',$type = '',  $num = 0, $data = array()){
            $balance_info  = get_balance($uid,$type);
            $dephp_11      = $balance_info['balance'] + $num;

            Db::name('member_balance')->where(['user_id' => $uid,'balance_type' => $balance_info['balance_type']])->update(['balance' => $dephp_11]);
           
            $dephp_12 = array('user_id' => $uid, 'balance_type' => $balance_info['balance_type'], 'old_balance' => $balance_info['balance'], 'balance' => $dephp_11,'create_time' => time(), 'account_id' => intval($data[0]), 'note' => $data[1]);
            Db::name('menber_balance_log')->insert($dephp_12);
    }

    public static function getBalance($uid = '',$type = ''){
        $balance  = Db::name('member_balance')->where(['user_id' => $uid ,'balance_type' => $type])->value('balance');
        return $balance;
    }

  
    public static  function getLevels(){
        $Leve = Db::table('member_level')->order('level')->select();
        return $Leve;
    }
    function getLevel($dephp_0){
        global $_W;
        if (empty($dephp_0)){
            return false;
        }
        $dephp_7 = m('member') -> getMember($dephp_0);
        if (empty($dephp_7['level'])){
            return array('discount' => 10);
        }
        $dephp_17 = pdo_fetch('select * from ' . tablename('sz_yi_member_level') . ' where id=:id and uniacid=:uniacid order by level asc', array(':uniacid' => $_W['uniacid'], ':id' => $dephp_7['level']));
        if (empty($dephp_17)){
            return array('discount' => 10);
        }
        return $dephp_17;
    }
  
    public static function getGroups(){
        $Group = Db::table('member_group')->order('id')->select();
        return $Group;
    }
    public static function getGroup($dephp_0){
        if (empty($dephp_0)){
            return false;
        }
        $dephp_7 = self::getMember($dephp_0);
        return $dephp_7['groupid'];
    }

    /**
     *  判断是否是puls会员
     */
    public function is_puls ($id = 0) {
        $sql = "select is_puls from member where id=$id";
        $is_puls = Db::query($sql);
        if ($is_puls['is_puls'] == 1){
            return true;
        }else{
            return false;
        }
    }

    /**
     *  成为puls会员
     * $type    渠道 1：订单渠道，2：直接支付渠道
     */
    public function create_puls ($id = 0,$order_id = 0,$type = 2) {
        if ($id && $type ==1 && $order_id){
            $get_order_goods_goodsid = Db::table('order_goods')->where('order_id',$order_id)->column('goods_id');
            $get_goods_ispuls_sum = Db::table('goods')->where(['id',['in',$get_order_goods_goodsid]])->sum('is_puls');
            if ($get_goods_ispuls_sum == 0){
                //订单没有商品开启升级puls会员选项
                return true;
            }
        }elseif ($id && $type == 2){

        }else{
            return false;
        }
        $update_sql = "update member set is_puls=1 where id=$id";
        $update = Db::query($update_sql);
        if ($update){
            return true;
        }else{
            return false;
        }
    }

}
