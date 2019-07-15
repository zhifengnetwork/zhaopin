<?php


namespace app\common\logic;

use app\common\model\Goods;
use app\common\model\Shop;
use app\common\model\UserAddress;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Db;
use app\common\model\SpecGoodsPrice;

/**
 * 积分商品计算和购买类
 * Class Integral
 * @package app\common\logic
 */
class Integral
{
    private $goods;
    private $specGoodsPrice;
    private $buyNum;
    private $user;
    private $userAddress;
    private $userMoney;
    private $shop;//自提点

    /**
     * 设置购买的商品
     * @param $goods_id
     */
    public function setGoodsById($goods_id){
        if($goods_id > 0){
            $this->goods = Goods::get($goods_id);
        }
    }

    /**
     * 设置购买的商品规格模型
     * @param $item_id
     */
    public function setSpecGoodsPriceById($item_id){
        if($item_id > 0){
            $this->specGoodsPrice = SpecGoodsPrice::get($item_id);
        }
    }

    /**
     * 设置购买多少件
     * @param $buyNum
     */
    public function setBuyNum($buyNum){
        $this->buyNum = $buyNum;
    }

    public function setUserById($user_id){
        if($user_id > 0){
            $this->user = Users::get($user_id);
        }
        return $this;
    }

    /**
     * 设置配送地址
     * @param $address_id
     * @return $this
     */
    public function setUserAddressById($address_id){
        if($address_id > 0){
            $this->userAddress = UserAddress::get($address_id);
        }
        return $this;
    }

    /**
     * 获取用户地址
     * @return mixed
     */
    public function getUserAddress()
    {
        return $this->userAddress;
    }

    /**
     *  设置使用余额
     * @param $userMoney
     */
    public function useUserMoney($userMoney){
        $this->userMoney = $userMoney;
    }

    public function setShopById($shop_id)
    {
        if($shop_id){
            $this->shop = Shop::get($shop_id);
        }
    }
    /**
     * 购买前检查
     * @throws TpshopException
     */
    public function checkBuy()
    {
        $isPointRate = tpCache('integral.is_point_rate');
        $isUseIntegral = tpCache('integral.is_use_integral');
        if($isPointRate != 1 || $isUseIntegral != 1){
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '商城暂时不能使用积分']);
        }
        if(empty($this->user)){
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '请登录']);
        }
        if(empty($this->goods)){
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '该商品不存在']);
        }
        if ($this->goods['is_on_sale'] != 1) {
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '商品已下架']);
        }
        if ($this->goods['exchange_integral'] <= 0) {
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '该商品不属于积分兑换商品']);
        }
        if ($this->goods['store_count'] == 0) {
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '商品库存为零']);
        }
        if ($this->buyNum > $this->goods['store_count']) {
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '商品库存不足，剩余' . $this->goods['store_count'] . '份']);
        }
        $total_integral = $this->goods['exchange_integral'] * $this->buyNum;
        if (empty($this->specGoodsPrice)) {
            $goods_spec_list = SpecGoodsPrice::all(['goods_id' => $this->goods['goods_id']]);
            if (count($goods_spec_list) > 0) {
                throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '请传递规格参数', 'result' => '']);
            }
            //没有规格
        } else {
            //有规格
            if ($this->buyNum > $this->specGoodsPrice['store_count']) {
                throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => '该商品规格库存不足，剩余' . $this->specGoodsPrice['store_count'] . '份']);
            }
        }
        $integral_use_enable = tpCache('shopping.integral_use_enable');
        //购买设置必须使用积分购买，而用户的积分不足以支付
        if ($total_integral > $this->user['pay_points'] && $integral_use_enable == 1) {
            throw new TpshopException('积分兑换', 0, ['status' => 0, 'msg' => "你的账户可用积分为:" . $this->user['pay_points']]);
        }
    }

    /**
     * 积分商品购买计算
     * @return Pay
     * @throws TpshopException
     */
    public function pay()
    {
        if (empty($this->userAddress)) {
            throw new TpshopException('积分兑换', 0,['status' => -3, 'msg' => '请先填写收货人信息', 'result' => '']);
        }
        $integralGoods = $this->goods;
        $total_integral = $this->goods['exchange_integral'] * $this->buyNum;//需要兑换的总积分
        if (empty($this->specGoodsPrice)) {
            //没有规格
            $integralGoods['goods_price'] = $this->goods['shop_price'];
            $integralGoods['sku'] = $this->goods['sku'];
        } else {
            //有规格
            $integralGoods['goods_price'] = $this->specGoodsPrice['price'];
            $integralGoods['spec_key'] = $this->specGoodsPrice['key'];// 商品规格
            $integralGoods['spec_key_name'] = $this->specGoodsPrice['key_name'];// 商品规格名称
            $integralGoods['sku'] = $this->specGoodsPrice['sku'];
        }
        $integralGoods['goods_num'] = $this->buyNum;
        $goodsList[0] = $integralGoods;
        $pay = new Pay();
        $pay->setUserId($this->user['user_id'])->setShopById($this->shop['shop_id'])->payGoodsList($goodsList)
            ->delivery($this->userAddress['district'])->usePayPoints($total_integral, true)->useUserMoney($this->userMoney);
        return $pay;
    }

}