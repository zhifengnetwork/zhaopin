<?php

/***
 * 充值api
 */
namespace app\api\controller;
use app\api\controller\RechargeNotify;
use Payment\Common\PayException;
use Payment\Notify\PayNotifyInterface;
use Payment\Notify\AliNotify;
use app\common\model\Member;

use \think\Model;
use \think\Config;
use \think\Db;
use \think\Env;
use \think\Request;

class Recharge extends ApiBase
{
    /**
     * 充值商品
     */

    public function good(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
        }
        $good_list  =  Db::name('recharge_good')->where(['state' => 1])->select();

        $this->ajaxReturn(['status' => 1 , 'msg'=>'充值商品','data'=>$good_list]);


        


    }

    /***
     * 充值接口
     */
    public function pay_recharge(){
        $user_id = $this->get_user_id();//用户ID
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
        }
        $member = Member::get($user_id);
        
        $recharge_type  = input('recharge_type',3); //2微信 3支付宝
        $good_id        = input('id',0);//商品ID
      
        if($good_id > 0){
            // 判断商品组是否存在
            $good = Db::name(['id' => $id, 'state' => 1])->find();
            !$good && $this->ajaxReturn(['status' => -2, 'msg'=>'商品不存在或者已失效','data'=>'']);
            $amount = $good['total_amount'];
        }else{
            $amount         = input('amount', 0);//自选金额
            //判断金额是否存在
            $data           = [];
            $data['amount'] = $amount;
    
            $rule = [
                'amount|金额' => '>:0|<:1000000',
            ];
            $validate = new Validate($rule);
            $result   = $validate->check($data);
            if (!$result) {
                $this->ajaxReturn(['status' => -2, 'msg'=>$validate->getError(),'data'=>'']);
            }
        }
        $order_sn = date('YmdHis',time()) . mt_rand(10000000,99999999);

        $data = [
            'order_sn' => $order_sn,
            'user_id'  => $user_id,
            'source'   => $pay_type,
            'status'   => 0,
            'create_time' => time(),
        ];
        // 启动事务
        Db::startTrans();

        $res = Db::name('recharge_order')->insert($data);

        if (!$res) {
             Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg'=>'充值失败','data'=>'']);
        }

        $rechData['order_no']        = $order_info['order_sn'];
        $rechData['body']            = '商城余额充值';
        $rechData['timeout_express'] = time() + 600;
        $rechData['amount']          = $amount;
        $rechData['subject']         = '余额充值';

        if($recharge_type == 2){//微信支付
            $rechData['openid']       = $member['openid'];
            $pay_config = Config::get('pay_config');
            $url        = Charge::run(PayConfig::ALI_CHANNEL_WAP, $pay_config, $rechData);
        }elseif($recharge_type == 3){//支付宝
            $rechData['goods_type']   = 0;//虚拟还是实物
            $rechData['client_ip']    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';// 客户地址
            $wxConfig = Config::get('wx_config');
            $url      = Charge::run(Config::WX_CHANNEL_WAP, $wxConfig, $rechData);
        }
        
        // 提交事务
        Db::commit();
           
    }


     /***
     * 充值回调
     */
    public function rech_notify(){
        $callback = new RechargeNotify();
        $config   = Config::get('pay_config');
        $ret      = Notify::run('ali_charge', $config, $callback);
        echo  $ret;
    }


    /***
     * 提现接口
     * 2微信 3支付宝
     */
    public function withdraw(){

        $user_id = $this->get_user_id();//用户ID
        if(!$user_id){
            $this->ajaxReturn(['status' => -2, 'msg'=>'用户不存在','data'=>'']);
        }
        $withdraw_type = input('withdraw_type',2);
        $amount        = input('amount',0);
        //判断金额是否存在
        $data           = [];
        $data['amount'] = $amount;

        $rule = [
            'amount|金额' => '>:0|<:1000000',
        ];
        $validate = new Validate($rule);
        $result   = $validate->check($data);
        if (!$result) {
            $this->ajaxReturn(['status' => -2, 'msg'=>$validate->getError(),'data'=>'']);
        }
        $member         = Member::get($user_id);
        $member_balance = Db::name('member_balance')->where(['user_id' => $user_id,'is_tixian' => 1])->field('sum(balance) as balance')->find();
        
        if($amount < $amount){
            $this->ajaxReturn(['status' => -2, 'msg'=>'超过可提现金额！' ,'data'=>'']);
        }

        if($withdraw_type == 2){//微信
            $account_name   =  '微信';
            $account_number =  $member['openid'];    
        }elseif($withdraw_type == 3){
            $account_name   =  '支付宝';
            $account_number =  $member['alipay'];
        }
        //提现申请
        $insert = [
            'user_id'        => $user_id,
            'money'          => $amount,
            'withdraw_type'  => $withdraw_type,
            'account_name'   => $account_name,
            'account_number' => $account_number,
            'taxfee'         => $amount * 0.006,//提现费率做成配置
            'status'         => 1,
            'create_time'    => time(),
        ];
        $res = Db::name('withdraw')->insert($insert);

        if($res !== false){
             $this->ajaxReturn(['status' => 1, 'msg'=>'申请成功,正在审核中！','data'=>'']);
        }
        
        $this->ajaxReturn(['status' => -2, 'msg'=>'申请失败,请稍后再试！','data'=>'']);
    }


}