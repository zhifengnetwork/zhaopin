<?php

namespace app\common\logic;

use app\common\model\Auction;
use app\common\model\Coupon;
use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 竞拍逻辑类
 */
class AuctionLogic extends Model
{
    protected $auction;//拼团活动模型
    protected $goods;//商品模型
    protected $specGoodsPrice;//商品规格模型

    public function __construct($goods, $specGoodsPrice)
    {
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
//        if($this->specGoodsPrice){
//            //活动商品有规格，规格和活动是一对一
//            $this->auction = Auction::get($specGoodsPrice['prom_id']);
//        }else{
            //活动商品没有规格，活动和商品是一对一
            $this->auction = Auction::get($goods['prom_id']);
//        }
        if ($this->auction) {
            //每次初始化都检测活动是否失效，如果失效就更新活动和商品恢复成普通商品
            if ($this->checkActivityIsEnd() && $this->auction['is_end'] == 0) {
                if($this->specGoodsPrice){
                    Db::name('spec_goods_price')->where('item_id', $this->specGoodsPrice['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    $goodsPromCount = Db::name('spec_goods_price')->where('goods_id', $this->specGoodsPrice['goods_id'])->where('prom_type','>',0)->count('item_id');
                    if($goodsPromCount == 0){
                        Db::name('goods')->where("goods_id", $this->specGoodsPrice['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    }
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecGoodsPrice::get($specGoodsPrice['item_id']);
                }else{
                    Db::name('goods')->where("goods_id", $this->GroupBuy['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                }
                $this->GroupBuy->is_end = 1;
                $this->GroupBuy->save();
                unset($this->goods);
                $this->goods = Goods::get($goods['goods_id']);
            }
        }
    }

    /**
     * 包含一个商品模型
     * @param $goods_id
     */
    public function setAuctionModel($auction_id)
    {
        if ($auction_id > 0) {
            $auctionModel = new Auction();
            $this->auction = $auctionModel::get($auction_id);
        }
    }

    public function getPromModel(){
        return $this->auction;
    }

    /**
     * 活动是否结束
     * @return bool
     */
    public function checkActivityIsAble(){

        return $this->checkActivityIsEnd();
//        if(empty($this->auction)){
//            return false;
//        }
//        if(time() > $this->auction['start_time'] && time() < $this->auction['end_time'] && $this->auction['is_end'] == 0){
//            return true;
//        }
//        return false;
    }

    /**
     * 活动是否结束
     * @return bool
     */
    public function checkActivityIsEnd(){
        if(empty($this->auction)){
            return true;
        }
        if(time() > $this->auction['end_time']){
            return true;
        }
        return false;
    }


    public function cartAuction(){

    }



    /**
     * 竞拍商品立即购买
     * @param $buyGoods
     * @return mixed
     * @throws TpshopException
     */
    public function buyNow($buyGoods){

        //活动是否已经结束
        if($this->auction['is_end'] == 1 || !empty($this->auction)){
            if($this->checkActivityIsAble()){
                $auctionPrice = Db::name('AuctionPrice')->where(['auction_id' => $this->auction['id'], 'is_out' => 2, 'pay_status' => 0])->find();
                if($auctionPrice['user_id'] == $buyGoods['user_id']){
                    $buyGoods['member_goods_price'] = $auctionPrice['offer_price'];
                    $buyGoods['prom_type'] = 8;
                    $buyGoods['prom_id'] = $this->auction['id'];
                }
            }
        }

        return $buyGoods;
    }


}