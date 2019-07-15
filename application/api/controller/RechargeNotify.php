<?php
namespace app\api\controller;
use Payment\Notify\PayNotifyInterface;
use Payment\Config;
use think\Loader;
use think\Db;

/**
 * @author: helei
 * @createTime: 2016-07-20 18:31
 * @description:
 */

/**
 * 客户端需要继承该接口，并实现这个方法，在其中实现对应的业务逻辑
 * Class TestNotify
 * anthor helei
 */
class RechargeNotify implements PayNotifyInterface
{
    public function notifyProcess(array $data)
    {
        $channel = $data['channel'];
        if ($channel === Config::ALI_CHARGE){// 支付宝支付
            // array (
            //     'notify_time' => '2019-05-16 19:40:33',
            //     'notify_type' => 'trade_status_sync',
            //     'notify_id' => '2019051600222194032091281017974707',
            //     'app_id' => '2019050264367537',
            //     'transaction_id' => '2019051622001491281034488845',
            //     'order_no' => '20190515213016563588',
            //     'out_biz_no' => '',
            //     'buyer_id' => '2088022531091287',
            //     'buyer_account' => '151****2455',
            //     'seller_id' => '2088531154918656',
            //     'seller_email' => 'gzyx5558@163.com',
            //     'trade_state' => 'success',
            //     'amount' => '0.01',
            //     'receipt_amount' => '0.01',
            //     'invoice_amount' => '0.01',
            //     'pay_amount' => '0.01',
            //     'point_amount' => '0.00',
            //     'refund_fee' => '',
            //     'subject' => '支付宝支付',
            //     'body' => 'ADS大声地说',
            //     'trade_create_time' => '2019-05-16 19:40:31',
            //     'pay_time' => '2019-05-16 19:40:32',
            //     'trade_refund_time' => '',
            //     'trade_close_time' => '',
            //     'channel' => 'ali_charge',
            //     'fund_bill_list' => 
            //     array (
            //       0 => 
            //       array (
            //         'amount' => '0.01',
            //         'fundChannel' => 'ALIPAYACCOUNT',
            //       ),
            //     ),
            //   )
            //修改订单状态
            $update = [
                'transaction_id' => $data['transaction_id'],
                'order_status'   => 1,
                'pay_status'     => 1,
                'pay_time'       => strtotime($data['pay_time']),
            ];

            Db::startTrans();

            $res = Db::name('recharge_order')->where(['order_sn' => $data['order_no']])->update($update);
            
            if($res == false){
                Db::rollback();
                return false;
            }

            //用户余额改变
            $balance = [
                'balance'            =>  Db::raw('balance+'.$data['amount'].''),
            ];
            $res =  Db::table('member_balance')->where(['user_id' => $user_id,'balance_type' => 0])->update($balance);

            if($res == false){
                Db::rollback();
                return false;
            }
            //todo::日志记录
        } elseif ($channel === Config::WX_CHARGE) {// 微信支付
        } elseif ($channel === Config::CMB_CHARGE) {// 招商支付
        } elseif ($channel === Config::CMB_BIND) {// 招商签约
        } else {
            // 其它类型的通知
        }
        // 执行业务逻辑，成功后返回true
        return true;
    }
}