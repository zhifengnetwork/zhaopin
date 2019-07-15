<?php


namespace app\common\logic\team;
use app\common\logic\Prom;
use app\common\model\TeamActivity;
use think\Db;
use app\common\model\SpecGoodsPrice;
use app\common\model\Goods;

/**
 * 拼团活动逻辑类
 */
class TeamActivityLogic extends Prom
{
    protected $team;//拼团模型
    protected $goods;//商品模型
    protected $specGoodsPrice;//商品规格模型
    public function __construct($goods,$specGoodsPrice)
    {
        parent::__construct();
        $this->goods = $goods;
        $this->specGoodsPrice = $specGoodsPrice;
        if($this->specGoodsPrice){
            //活动商品有规格，规格和活动是一对一
            $this->team = TeamActivity::get($specGoodsPrice['prom_id'],'',true);
        }else{
            //活动商品没有规格，活动和商品是一对一
            $this->team = TeamActivity::get($this->goods['prom_id'],'',true);
        }
        if ($this->team) {
            //每次初始化都检测活动是否失效，如果失效就恢复商品成普通商品
            if ($this->checkActivityIsEnd()) {
                if($this->specGoodsPrice){
                    Db::name('spec_goods_price')->where('item_id', $this->specGoodsPrice['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    $goodsPromCount = Db::name('spec_goods_price')->where('goods_id', $this->specGoodsPrice['goods_id'])->where('prom_type','>',0)->count('item_id');
                    if($goodsPromCount == 0){
                        Db::name('goods')->where("goods_id", $this->specGoodsPrice['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                    }
                    unset($this->specGoodsPrice);
                    $this->specGoodsPrice = SpecGoodsPrice::get($specGoodsPrice['item_id'],'',true);
                }else{
                    Db::name('goods')->where("goods_id", $this->team['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
                }
                db('team_goods_item')->where('team_id', $this->team['team_id'])->update(['deleted' => 1]);
                unset($this->goods);
                $this->goods = Goods::get($goods['goods_id']);
            }
        }
    }
    public function getPromModel(){
        return $this->team;
    }

    public function getGoodsInfo(){
        return $this->goods;
    }

    public function getActivityGoodsInfo(){
        if($this->specGoodsPrice){
            //活动商品有规格，规格和活动是一对一
            $activityGoods = $this->specGoodsPrice;
            $activityGoods['shop_price']=$activityGoods['price'];
        }else{
            //活动商品没有规格，活动和商品是一对一
            $activityGoods = $this->goods;
        }
        return $activityGoods;
    }

    public function checkActivityIsAble(){
        return $this->IsAble();
    }
    /**
     * 活动是否结束
     * @return bool
     */
    public function checkActivityIsEnd(){
        if(empty($this->team)){
            return true;
        }
        if($this->team['team_type'] == 2 && $this->team['is_lottery'] == 1){
            return true;
        }
        return false;
    }
    public function IsAble(){
        if(empty($this->team)){
            return false;
        }
        if($this->team['status'] != 1){
            return false;
        }
        if($this->team['team_type'] == 2 && $this->team['is_lottery'] == 1){
            return false;
        }
        return true;
    }
    /**
     * @param $buyGoods
     * @return array
     */
    public function buyNow($buyGoods){
        $buyGoods['prom_type'] =0;
        $buyGoods['prom_id'] = 0;
        return $buyGoods;
    }
}