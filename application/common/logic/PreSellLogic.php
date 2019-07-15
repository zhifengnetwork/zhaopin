<?php


namespace app\common\logic;

use app\common\model\Cart;
use app\common\model\Goods;
use app\common\model\PreSell;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use think\db;

/**
 * 预售
 * Class CatsLogic
 * @package common\Logic
 */
class PreSellLogic extends Prom
{
    protected $preSell;//预售活动模型
    protected $goods;//商品模型
    protected $specGoodsPrice;//商品规格模型

    public function __construct($goods, $specGoodsPrice)
    {
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
        $this->initProm();
    }

    public function initProm()
    {
        // TODO: Implement initProm() method.
        if($this->specGoodsPrice){
            //活动商品有规格，规格和活动是一对一
            $this->preSell = PreSell::get($this->specGoodsPrice['prom_id'],'',false);
        }else{
            //活动商品没有规格，活动和商品是一对一
            $this->preSell = PreSell::get($this->goods['prom_id'],'',false);
        }
        if ($this->preSell) {
            //每次初始化都检测活动是否结束，如果失效就更新活动和商品恢复成普通商品
            if ($this->checkActivityIsEnd() && $this->preSell['is_finished'] == 0) {
                if($this->specGoodsPrice){
                    Db::name('spec_goods_price')->where('item_id', $this->specGoodsPrice['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    $goodsPromCount = Db::name('spec_goods_price')->where('goods_id', $this->specGoodsPrice['goods_id'])->where('prom_type','>',0)->count('item_id');
                    if($goodsPromCount == 0){
                        Db::name('goods')->where("goods_id", $this->specGoodsPrice['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    }
                    $item_id = $this->specGoodsPrice['item_id'];
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecGoodsPrice::get($item_id['item_id'],'',true);
                }else{
                    Db::name('goods')->where("goods_id", $this->preSell['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                }
                $this->preSell->is_finished = 1;
                $this->preSell->save();
                $goods_id = $this->goods['goods_id'];
                unset($this->goods);
                $this->goods = Goods::get($goods_id,'',true);
            }
        }
    }

    /**
     * 活动是否正在进行
     * @return bool
     */
    public function checkActivityIsAble(){
        if(empty($this->preSell)){
            return false;
        }
        if(time() > $this->preSell['sell_start_time'] && time() < $this->preSell['sell_end_time'] && $this->preSell['is_finished'] == 0){
            return true;
        }
        return false;
    }

    /**
     * 活动是否结束
     * @return bool
     */
    public function checkActivityIsEnd(){
        if(empty($this->preSell)){
            return true;
        }
        if($this->preSell['deposit_goods_num'] >= $this->preSell['stock_num']){
            return true;
        }
        if(time() > $this->preSell['sell_end_time']){
            return true;
        }
        return false;
    }

    /**
     * 获取单个抢购活动
     * @return static
     */
    public function getPromModel(){
        return $this->preSell;
    }

    /**
     * 获取商品原始数据
     * @return static
     */
    public function getGoodsInfo()
    {
        return $this->goods;
    }

    /**
     * 获取商品转换活动商品的数据
     * @return static
     */
    public function getActivityGoodsInfo(){
        if($this->specGoodsPrice){
            //活动商品有规格，规格和活动是一对一
            $activityGoods = $this->specGoodsPrice->toArray();
        }else{
            //活动商品没有规格，活动和商品是一对一
            $activityGoods = $this->goods->toArray();
        }
        $activityGoods['activity_title'] = $this->preSell['title'];
        $activityGoods['market_price'] = $this->goods['shop_price'];
        $activityGoods['shop_price'] = $this->preSell['ing_price'];//预售价格
        $activityGoods['deposit_price'] = $this->preSell['deposit_price'];//订金
        $activityGoods['balance_price'] = $this->preSell['ing_price'] - $this->preSell['deposit_price'];//尾款
        $activityGoods['store_count'] = $this->preSell['stock_num'] - $this->preSell['deposit_goods_num'];
        $activityGoods['start_time'] = $this->preSell['sell_start_time'];
        $activityGoods['end_time'] = $this->preSell['sell_end_time'];
        $activityGoods['price_ladder'] = $this->preSell['price_ladder'];
        $activityGoods['ing_amount'] = $this->preSell['ing_amount'];
        return $activityGoods;
    }

    /**
     * 这里不会用到，预售商品不走购物车
     * 该活动是否已经失效
     */
    public function IsAble(){
        return true;
    }

    /**
     * 组装成和购物车表一样的数据记录
     * @param $goods_num
     * @return array
     * @throws TpshopException
     */
    public function buyNow($goods_num)
    {
        $user = session('user');
        if ($this->checkActivityIsEnd()) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '活动已结束', 'result' => '']);
        }
        if (!$this->checkActivityIsAble()) {
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '活动已失效', 'result' => '']);
        }
        if($goods_num > $this->preSell['stock_num']){
            throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '预售库存不足，剩余'.$this->preSell['stock_num'].'件', 'result' => '']);
        }
        $cartInfo = [
            'user_id'=>$user['user_id'],
            'session_id'=>session_id(),
            'goods_id'=>$this->goods['goods_id'],
            'goods_sn'=>$this->goods['goods_sn'],
            'goods_name'=>$this->goods['goods_name'],
            'market_price'=>$this->goods['market_price'],
            'selected'=>1,
            'add_time'=>time(),
            'prom_type'=>4,
            'prom_id'=>$this->preSell['pre_sell_id'],
            'goods_num'=>$goods_num,
        ];
        $cartInfo['goods_price'] = $this->preSell['ing_price'];
        if($this->preSell['deposit_price'] > 0){
            $cartInfo['member_goods_price'] = $this->preSell['deposit_price'];
        }else{
            $cartInfo['member_goods_price'] = $this->preSell['ing_price'];
        }
        if($this->specGoodsPrice){
            $cartInfo['spec_key'] = $this->specGoodsPrice['key'];
            $cartInfo['spec_key_name'] = $this->specGoodsPrice['key_name'];
            $cartInfo['bar_code'] = $this->specGoodsPrice['bar_code'];
            $cartInfo['sku'] = $this->specGoodsPrice['sku'];
            if($goods_num > $this->specGoodsPrice['store_count']){
                throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '商品规格库存不足，剩余'.$this->specGoodsPrice['store_count'].'件', 'result' => '']);
            }
        }else{
            $cartInfo['spec_key'] = '';
            $cartInfo['spec_key_name'] = '';
            $cartInfo['bar_code'] = '';
            $cartInfo['sku'] = $this->goods['sku'];
            if($goods_num > $this->goods['store_count']){
                throw new TpshopException('立即购买', 0, ['status' => 0, 'msg' => '商品库存不足，剩余'.$this->goods['store_count'].'件', 'result' => '']);
            }
        }
        $cart = new Cart();
        $cartInfo['total_fee'] = $cart->getTotalFeeAttr(null, $cartInfo);
        $cartInfo['goods_fee'] = $cart->getGoodsFeeAttr(null, $cartInfo);
        $cartInfo['cut_fee'] = $cart->getCutFeeAttr(null, $cartInfo);
        $cartInfo['goods']['weight'] = $this->goods['weight'];
        $cartInfo['weight'] = $this->goods['weight'];
        return $cartInfo;
    }

    public function getPromId(){
        if($this->preSell){
            return $this->preSell['pre_sell_id'];
        }else{
            return null;
        }
    }
}