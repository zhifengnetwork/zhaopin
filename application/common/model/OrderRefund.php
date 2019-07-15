<?php
namespace app\common\model;
use Payment\Common\PayException;
use Payment\Client\Refund;
use Payment\Config;
use Payment\Common\WxConfig;
use think\Model;
use think\Db;

class OrderRefund extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    /***
     * 订单退款
     * pay_type 1支付宝 2微信 3余额
     */
    public static function refund_obj($data){
        require_once ROOT_PATH.'vendor/riverslei/payment/autoload.php';

        $pay_type      = $data['pay_type'];//支付类型
        $order_sn      = $data['order_sn'];//订单号
        $order_amount  = $data['order_amount'];//退款金额
        $refund_sn     = $data['refund_sn'];//退款订单号
        if($pay_type == 1){//支付宝退款
            $paydata = [
                'out_trade_no' => $order_sn,
                'trade_no'     => '',// 支付宝交易号， 与 out_trade_no 必须二选一
                'refund_fee'   => $order_amount,
                'reason'       => '商品退款',
                'refund_no'    => $refund_sn,
            ];
            $pay_config = Config::get('pay_config');

        }else if($pay_type == 2){//微信退款

            $paydata = [
                'out_trade_no'   => $order_sn,
                'total_fee'      => $order_amount,
                'refund_fee'     => $order_amount,
                'refund_no'      => $refund_sn,
                'refund_account' => WxConfig::REFUND_RECHARGE,// REFUND_RECHARGE:可用余额退款  REFUND_UNSETTLED:未结算资金退款（默认）
            ];
            $pay_config = Config::get('wx_config');
        }else if($pay_type == 3){//余额退款
            $balance = [
                'balance'       =>  Db::raw('balance-'.$order_amount.''),
            ];
            $res =  Db::table('member_balance')->where(['user_id' => $data['user_id'],'balance_type' => 0])->update($balance);
            if(!$res){
                Db::rollback();
            }
            //改变订单状态
            $update = [
                'order_status'  => 7,

            ];
           $status = Db::name('order')->where(['order_sn' => $order_sn])->update($update);

            if(!$status){
                Db::rollback();
            }
            // 提交事务
            Db::commit();
            
        }
        try {
            $ret = Refund::run(Config::ALI_REFUND, $pay_config, $paydata);
        } catch (PayException $e) {
            echo $e->errorMessage();
            exit;
        }
        $res = json_encode($ret, JSON_UNESCAPED_UNICODE);
                                 
    }
}
