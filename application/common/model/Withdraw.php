<?php
namespace app\common\model;
use Payment\Common\PayException;
use Payment\Client\Transfer;
use Payment\Config;
use think\Model;
use think\Db;

class Withdraw extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    /***
     * 提现
     * withdraw_type 2微信 3支付宝
     */
    public static function withdrawcon($order){

        $withData['trans_no']      =  time();
        $withData['amount']        =  $order['money'];
        if($order['withdraw_type'] == 2){//微信
            $withData['desc']      = '提现到微信';
            $withData['openid']    =  $order['account_number'];
            $withData['check_name']=  'NO_CHECK';
            $withData['payer_real_name']=  '何磊';
            $withData['spbill_create_ip']=  '127.0.0.1';
            try {
                $ret = Transfer::run(Config::ALI_TRANSFER, $aliConfig, $withData);
            } catch (PayException $e) {
                return ['status' => -1,'msg' => $e->errorMessage()];
            }
        }elseif($order['withdraw_type'] == 3){
            $withData['remark']          = '提现到支付宝';
            $withData['payee_account']   =  $order['account_number'];
            $withData['payee_type']      = 'ALIPAY_LOGONID';
            $withData['payer_show_name'] = '一个未来的富豪';
            try {
                $ret = Transfer::run(Config::WX_TRANSFER, $wxConfig, $withData);
            } catch (PayException $e) {
                return ['status' => -1,'msg' => $e->errorMessage()];
            }
        }
        $reslt = json_encode($ret, JSON_UNESCAPED_UNICODE);
        if($reslt['code'] == '成功'){
             return ['status' => 1,'msg' => '提现成功'];
        }else{
             return ['status' => -1,'msg' => '提现失败'];
        }
    }
}
