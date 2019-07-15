<?php
namespace app\api\model;

use app\common\model\Channel;
use app\common\model\ChannelType;
use app\common\model\ChannelStatistics;
use app\common\model\ChannelPlatform;
use org\MoPay;
use think\Db;
use think\Model;

/**
 * 支付模型
 */
class Pay extends Model
{

    /**
     * 创建墨支付订单
     */
    public static function createMoPayOrder($orderId, $amount, $channel)
    {
        $xxp = new MoPay($data['md5key'], $data['url']); // 用户私钥 支付地址
        $xxp->setParams('mchId', $data['mchid']); // 商户ID
        $xxp->setParams('productId', $data['productid']); // 产品ID
        $xxp->setParams('passageId', $data['passageid']); // 通道ID
        $xxp->setParams('appId', $data['appid']); // 应用ID

        $xxp->setParams('mchOrderNo', $orderId); // 订单号，保证唯一
        $xxp->setParams('amount', $amount); // 订单金额
        $xxp->setParams('notifyUrl', 'http://' . $_SERVER['HTTP_HOST'] . '/pay/moPayNotifyUrl'); // HTTP_HOST // 回调地址

        $xxp->setParams('param1', $data['id']); // 测试
        $xxp->setParams('clientIp', get_real_ip()); // 测试
        $xxp->setParams('extra', '{}'); // 测试
        $pay_url = $xxp->trade(); # 下单
        return $pay_url;
    }

    /**
     * 创建众宝支付订单
     */
    public static function createZonBaoOrder($orderid, $amount, $platform_id, $type_id)
    {
        // 获取支付平台配置
        $config = ChannelPlatform::where('id', $platform_id)->field('config')->find();
        $config = $config->config;

        // 获取支付类型号
        $type_config = ChannelType::where('id', $type_id)->field('config')->find();
        $pay_type = $type_config->config['pay_type'];

        $pay_url = $config['pay_url']; //支付地址
        $merKey  = $config['key']; //密钥
        $merchantid  = $config['merchantid']; //密钥
        $order   = [
            'merchantid'   => $merchantid, //商户id
            'paytype'      => $pay_type, //支付类型
            'amount'       => $amount, //支付金额
            'orderid'      => $orderid, //商户订单号
            'notifyurl'    => 'http://' . $_SERVER['HTTP_HOST'] . '/pay/zonBaoNotifyUrl', //回调地址
            'request_time' => date('YmdHis'), //系统请求时间
            'returnurl'    => 'http://' . $_SERVER['HTTP_HOST'] . '/pay/zonBaoNotifyUrl', //支付成功后返回地址
            'israndom'     => 'N', //如果值为Y，则启用订单风控保护规则  配置
            'isqrcode'     => 'N', //如果值为Y，则单独返回二维码，否则直接采用众宝扫码平台,如果传入为空或N则采用众宝收银台
            'desc'         => '', //备注消息
        ];
        $order['sign'] = self::sign($order, $merKey);
 
        header("content-Type: text/html; charset=UTF-8");
        echo '
                <form action="' . $pay_url . '" method="post" name="orderForm">
                <input type="hidden" name="merchantid"  value="' . $order['merchantid'] . '">
                <input type="hidden" name="paytype"  value="' . $order['paytype'] . '">
                <input type="hidden" name="amount"  value="' . $order['amount'] . '">
                <input type="hidden" name="orderid"  value="' . $order['orderid'] . '">
                <input type="hidden" name="notifyurl"  value="' . $order['notifyurl'] . '">
                <input type="hidden" name="request_time"  value="' . $order['request_time'] . '">
                <input type="hidden" name="returnurl"  value="' . $order['returnurl'] . '">
                <input type="hidden" name="israndom"  value="' . $order['israndom'] . '">
                <input type="hidden" name="isqrcode"  value="' . $order['isqrcode'] . '">
                <input type="hidden" name="desc"  value="' . $order['desc'] . '">
                <input type="hidden" name="sign"  value="' . $order['sign'] . '">
                </form>
                <script type="text/javascript">
                    setTimeout(function(){document.orderForm.submit();},1000)
                </script>
            ';
    }

    /**
     * 众宝签名
     */
    public static function sign($reqData, $merKey)
    {
        $strToSign = '';
        foreach ($reqData as $key => $val) {
            if ($key == 'returnurl') {
                break;
            }
            $strToSign = $strToSign . $key . "=" . $val . "&";
        }
        $strToSign = $strToSign . 'key=' . $merKey;

        //md5加密
        $strEncrypt = strtolower(md5($strToSign));
        return $strEncrypt;
    }

}
