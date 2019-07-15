<?php


namespace app\common\logic\team;

use app\common\logic\CouponLogic;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\model\Goods;
use app\common\model\Order;
use app\common\model\OrderGoods;
use app\common\model\SpecGoodsPrice;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use app\common\model\UserAddress;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Db;

/**
 * 拼团活动逻辑类
 */
class TeamOrder
{

    private $order;
    private $orderGoods;
    private $teamActivity;
    private $user_id;
    private $user;
    private $teamFollow;
    private $teamFound;
    private $goods;
    private $spec_goods_price;
    private $payPsw;

    private $pay;

    public function __construct($user_id, $order_id)
    {
        $this->pay = new Pay();
        $this->user_id = $user_id;
        $this->order = Order::get(['order_id' => $order_id, 'prom_type' => 6, 'user_id' => $user_id]);
        if (empty($this->order)) {
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该订单已关闭或者不存在','code' => '808', 'result' => '']);
        }
        $OrderGoods = new OrderGoods();
        $this->orderGoods = $OrderGoods->where(['order_id' => $order_id, 'prom_type' => 6])->find();
        if (empty($this->orderGoods)) {
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该订单失效或不存在', 'code' => '808','result' => '']);
        }
        $TeamActivity = new TeamActivity();
        $this->teamActivity = $TeamActivity->where(['team_id'=> $this->orderGoods['prom_id']])->find();
        if(empty($this->teamActivity)){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '订单失效或不存在', 'code' => '808','result' => '']);
        }
        $User = new Users();
        $this->user = $User->where(['user_id' => $user_id])->find();
        $TeamFollow = new TeamFollow();
        $TeamFound = new TeamFound();
        $this->teamFollow = $TeamFollow->where(['order_id' => $order_id, 'follow_user_id' => $this->user_id])->find();
        if(empty($this->teamFollow)){
            $this->teamFound = $TeamFound->where(['order_id' => $order_id, 'user_id' => $this->user_id])->find();
        }else{
            $this->teamFound = $TeamFound->where(['found_id' => $this->teamFollow['found_id']])->find();
        }
        $Goods = new Goods();
        $this->goods = $Goods->where(['goods_id'=>$this->teamActivity['goods_id']])->find();
        if($this->orderGoods['spec_key']){
            $this->spec_goods_price = SpecGoodsPrice::get(['goods_id'=>$this->teamActivity['goods_id'], 'key'=>$this->orderGoods['spec_key']]);
        }
    }

    public function useUserAddressById($address_id)
    {
        if(empty($this->order['province'])){
            if(empty($address_id)){
                throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '请选择地址', 'code' => 809, 'result' => '']);
            }else{
                $UserAddress = new UserAddress();
                $userAddress = $UserAddress->where(['address_id'=>$address_id,'user_id'=>$this->user_id])->find();
                if(empty($userAddress)){
                    throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '请选择地址', 'result' => []]);
                }
                $this->order['consignee'] = $userAddress['consignee'];
                $this->order['country'] = $userAddress['country'];
                $this->order['province'] = $userAddress['province'];
                $this->order['city'] = $userAddress['city'];
                $this->order['district'] = $userAddress['district'];
                $this->order['twon'] = $userAddress['twon'];
                $this->order['address'] = $userAddress['address'];
                $this->order['zipcode'] = $userAddress['zipcode'];
                $this->order['email'] = $userAddress['email'];
                $this->order['mobile'] = $userAddress['mobile'];
                $this->pay->delivery($userAddress['district']);
            }
        }else{
            $this->pay->delivery($this->order['district']);
        }
    }

    /**
     *  更改购买数量
     * @param $goods_num
     * @throws TpshopException
     */
    public function changNum($goods_num)
    {
        if($this->teamActivity['buy_limit'] != 0 && $goods_num > $this->teamActivity['buy_limit']){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '购买数已超过该活动单次购买限制数('.$this->teamActivity['buy_limit'].'个)', 'result' => []]);
        }
        if($goods_num > $this->orderGoods['goods_num']){
            $add_goods_num = $goods_num;
            if($this->teamActivity['team_type'] != 2){
                if(tpCache('shopping.reduce') == 1){
                    $add_goods_num = $goods_num - $this->orderGoods['goods_num'];
                }
            }
            if($this->spec_goods_price){
                if($add_goods_num > $this->spec_goods_price['store_count']){
                    throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '商品库存不足，剩余'.$this->spec_goods_price['store_count'], 'result' => []]);
                }
            }else{
                if($add_goods_num > $this->goods['store_count']){
                    throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '商品库存不足，剩余'.$this->goods['store_count'], 'result' => []]);
                }
            }
        }
        //已经使用优惠券/积分/余额支付的订单不能更改数量
        if($this->orderGoods['goods_num'] != $goods_num){
            if($this->order['user_money'] > 0 || $this->order['coupon_price'] > 0 || $this->order['integral'] > 0){
                throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '使用优惠券/积分/余额支付的订单不能更改数量', 'result' => []]);
            }
            $this->orderGoods['goods_num'] = $goods_num;
            $this->order['goods_price'] = round($this->orderGoods['member_goods_price'] * $goods_num, 2);
            $this->order['order_amount'] = $this->getOrderAmount();
            $this->order['total_amount'] = $this->getTotalAmount();
        }

    }

    public function useUserMoney($user_money)
    {
        $this->pay->useUserMoney($user_money);
    }

    public function usePayPoints($pay_points, $port = "mobile")
    {
        if ($this->order['integral_money'] == 0) {
            $this->pay->usePayPoints($pay_points, false, $port);
        }
    }
    public function useCouponById($coupon_id){
        if($coupon_id){
            if($this->order['coupon_price'] > 0){
                throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该订单已使用过优惠券不能更改优惠券', 'result' => []]);
            }
            $this->pay->useCouponById($coupon_id);
        }
    }
    /**
     * 计算订单价
     * @return mixed
     */
    private function getOrderAmount(){
        $order_amount = round((($this->order->goods_price + $this->order->shipping_price) - ($this->order->user_money + $this->order->coupon_price + $this->order->integral_money)),2);
        return $order_amount;
    }

    private function getTotalAmount(){
        $total_amount = round(($this->order->goods_price + $this->order->shipping_price),2);
        return $total_amount;
    }

    public function getOrder()
    {
        if ($this->pay->getShippingPrice() > 0) {
            $this->order['shipping_price'] = $this->pay->getShippingPrice();
        }
        if ($this->pay->getCouponPrice() > 0) {
            //这个判断只是用于第二次支付时，优惠券面额大于应付金额。
            if($this->pay->getOrderAmount() < 0){
                $this->order['coupon_price'] = $this->pay->getCouponPrice() + $this->pay->getOrderAmount();
            }else{
                $this->order['coupon_price'] = $this->pay->getCouponPrice();
            }
        }
        if ($this->pay->getUserMoney() > 0) {
            $this->order['user_money'] = round($this->order['user_money'] + $this->pay->getUserMoney(), 2);
        }
        if($this->pay->getIntegralMoney() > 0){
            $this->order['integral_money'] = $this->order['integral_money'] + $this->pay->getIntegralMoney();
            $this->order['integral'] = $this->order['integral'] + $this->pay->getPayPoints();
        }
        //小于零的情况为第二次支付时，优惠券面额大于应付金额。
        if($this->pay->getOrderAmount() < 0){
            $this->order['order_amount'] = 0.00;
        }else{
            $this->order['order_amount'] = $this->pay->getOrderAmount();
        }
        $this->order['total_amount'] = $this->pay->getTotalAmount();
        return $this->order;
    }

    public function getOrderGoods()
    {
        return $this->orderGoods;
    }

    public function setUserNote($user_note)
    {
        if($user_note){
            $this->order['user_note'] = $user_note;
        }
    }
    public function setPayPsw($pay_psw)
    {
        if($pay_psw){
            $this->payPsw = $pay_psw;
        }
    }
    public function submit()
    {
        $placeOrder = new PlaceOrder($this->pay);
        $placeOrder->setPayPsw($this->payPsw);
        $placeOrder->check();
        //支付方式，可能是余额支付或积分兑换，后面其他支付方式会替换
        if($this->order['integral'] > 0 || $this->order['user_money'] > 0){
            $this->order['pay_name'] = $this->order['user_money']>0 ? '余额支付' : '积分兑换';
        }
        $this->order->save();
        if($this->order['order_amount'] == 0){
            $team = new \app\common\logic\team\Team();
            $team->setTeamActivityById($this->order['prom_id']);
            $team->setOrder($this->order);
            $team->doOrderPayAfter();
            update_pay_status($this->order['order_sn']); // 这里刚刚下的订单必须从主库里面去查
        }
        $this->orderGoods->save();
        $placeOrder->setOrder($this->order);
        $placeOrder->deductionCoupon();
        $placeOrder->changUserPointMoney($this->order);
    }

    public function pay()
    {
        if($this->order['pay_status'] == 1){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该订单已支付成功', 'code' => 810, 'result' => '']);
        }
        if($this->order['pay_status'] == 3){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该订单支付失败', 'code' => 810, 'result' => '']);
        }
        if($this->order['order_status'] == 3){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该订单已取消', 'code' => 810, 'result' => '']);
        }
        if(empty($this->goods) || $this->goods['is_on_sale'] == 0){
            throw new TpshopException('拼团订单', 0, ['status'=>0,'msg'=>'该商品不存在或者已下架','result'=>[]]);
        }
        if($this->teamFound['team_id'] != $this->teamActivity['team_id']){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该拼单数据不存在或已失效', 'result' => '']);
        }
//        if($this->teamFound['join'] >= $this->teamFound['need']){
//            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该单已成功结束', 'result' => '']);
//        }
        if(time() - $this->teamFound['found_time'] > $this->teamActivity['time_limit']){
            throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该拼单已过期', 'result' => '']);
        }
        if(!empty($this->teamFollow)){
            //查看是否超时未支付。如果超时，就将该订单做取消订单处理。
            $team_order_limit_time = tpCache('shopping.team_order_limit_time');
            $limitTime = empty($team_order_limit_time) ? 1800 : $team_order_limit_time;
            if(((time() - $this->order['add_time']) > $limitTime) && ($this->order['pay_status'] == 0 && $this->order['order_status'] == 0)){
                $orderLogic = new OrderLogic();
                $orderLogic->cancel_order($this->teamFollow['follow_user_id'], $this->teamFollow['order_id']);
                throw new TpshopException('拼团订单', 0, ['status' => 0, 'msg' => '该拼单超时未支付已取消', 'result' => '']);
            }
        }
        $this->pay->setUserId($this->user_id);
        $this->pay->payOrder([$this->orderGoods]);
        $cut_order_amount = $this->order['integral_money'] + $this->order['user_money'] +  $this->order['coupon_price'];
        $this->pay->cutOrderAmount($cut_order_amount);//减去已使用的
    }

}