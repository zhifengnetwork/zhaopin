<?php


namespace app\common\logic;

use app\common\model\PreSell as PreSellModel;
use think\db;

/**
 * 预售
 * Class CatsLogic
 * @package common\Logic
 */
class PreSell
{
    private $pre_sell_id;
    private $preSell;
    private $order;

    public function setPreSellById($pre_sell_id)
    {
        if($pre_sell_id > 0){
            $this->pre_sell_id = $pre_sell_id;
            $this->preSell = PreSellModel::get($pre_sell_id);
        }
    }

    public function setOrder($order)
    {
        $this->order = $order;
    }

    public function doOrderPayAfter()
    {
        $orderGoods = Db::name('order_goods')->where(['order_id'=>$this->order['order_id']])->find();
        //支付尾款
        if($this->order['pay_status'] == 2){
            $this->order['pay_status'] = 1;
            $this->order['pay_time'] = time();
            $this->order->save();
        }
        if($this->order['pay_status'] == 0){
            if($this->preSell['deposit_price'] > 0){
                //付订金
                $OrderLogic = new OrderLogic();
                $this->order['order_sn'] = $OrderLogic->get_order_sn();
                $this->order['pay_status'] = 2;
                $this->order['paid_money'] = $this->preSell['deposit_price'] * $orderGoods['goods_num'];
            }else{
                //全额
                $this->order['pay_status'] = 1;
            }
            $this->order['pay_time'] = time();
            $this->preSell['deposit_goods_num'] = $this->preSell['deposit_goods_num'] + $orderGoods['goods_num'];
            $this->preSell['deposit_order_num'] = $this->preSell['deposit_order_num'] + 1;
            $this->preSell->save();
            $this->order->save();
        }
    }
}