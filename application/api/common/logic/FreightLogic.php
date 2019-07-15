<?php


namespace app\common\logic;

use app\common\model\FreightConfig;
use app\common\model\FreightRegion;
use app\common\model\FreightTemplate;
use app\common\model\Goods;
use app\common\model\Store;
use app\common\util\TpshopException;
use think\Model;
use think\Db;
/**
 * 运费 逻辑定义
 * Class CatsLogic
 * @package common\Logic
 */
class FreightLogic extends Model
{
    protected $goods;//商品模型
    protected $regionId;//地址
    protected $goodsNum;//件数
    private $freightTemplate;
    private $freight = 0;


    /**
     * 包含一个商品模型
     * @param $goods
     */
    public function setGoodsModel($goods)
    {
        $this->goods = $goods;
        $FreightTemplate = new FreightTemplate();
        $this->freightTemplate = $FreightTemplate->where(['template_id' => $this->goods['template_id']])->find();
    }

    /**
     * 设置地址id
     * @param $regionId
     */
    public function setRegionId($regionId)
    {
        $this->regionId = $regionId;
    }

    /**
     * 设置商品数量
     * @param $goodsNum
     */
    public function setGoodsNum($goodsNum)
    {
        $this->goodsNum = $goodsNum;
    }

    /**
     * 进行一系列运算
     * @throws TpshopException
     */
    public function doCalculation()
    {
        if ($this->goods['is_free_shipping'] == 1) {
            $this->freight = 0;
        }else{
            $freightRegion = $this->getFreightRegion();
            $freightConfig = $this->getFreightConfig($freightRegion);
            //计算价格
            switch ($this->freightTemplate['type']) {
                case 1:
                    //按重量
                    $total_unit = (array_key_exists('total_weight', $this->goods)) ? $this->goods['total_weight'] : $this->goods['weight'] * $this->goodsNum;//总重量
                    break;
                case 2:
                    //按体积
                    $total_unit = (array_key_exists('total_volume', $this->goods)) ? $this->goods['total_volume'] : $this->goods['volume'] * $this->goodsNum;//总体积
                    break;
                default:
                    //按件数
                    $total_unit = $this->goodsNum;
            }
            $this->freight = $this->getFreightPrice($total_unit, $freightConfig);
        }
    }

    /**
     * 是否支持配送
     * @return bool|true
     */
    public function checkShipping(){
        if($this->goods['is_free_shipping'] == 0){
            $freightRegion = $this->getFreightRegion();
            $freightConfig = $this->getFreightConfig($freightRegion);
            if(empty($freightConfig)){
                return false;
            }else{
                return true;
            }
        }else{
            return true;
        }
    }

    /**
     * 获取运费
     * @return int
     */
    public function getFreight()
    {
        return $this->freight;
    }

    /**
     * 根据总量和配置信息获取运费
     * @param $total_unit
     * @param $freight_config
     * @return mixed
     */
    private function getFreightPrice($total_unit,$freight_config){
        $total_unit = floatval($total_unit);
        if($total_unit > $freight_config['first_unit'] && $freight_config['continue_unit']>0){
            $average = ceil(($total_unit-$freight_config['first_unit']) / $freight_config['continue_unit']);
            $freight_price = $freight_config['first_money'] + $freight_config['continue_money'] * $average;
        }else{
            $freight_price = $freight_config['first_money'];
        }
        return $freight_price;
    }


    /**
     * @param $freightRegion
     * @return array|false|null|\PDOStatement|string|Model
     */
    private function getFreightConfig($freightRegion){
        //还找不到就去看下模板是否启用默认配置
        if (empty($freightRegion)) {
            if ($this->freightTemplate['is_enable_default'] == 1) {
                $FreightConfig = new FreightConfig();
                $freightConfig = $FreightConfig->where(['template_id' => $this->goods['template_id'], 'is_default' => 1])->find();
                return $freightConfig;
            }else{
                return null;
            }
        } else {
            return $freightRegion['freightConfig'];
        }
    }

    /**
     * 获取区域配置
     */
    private function getFreightRegion(){
        //先根据$region_id去查找
        $FreightRegion = new FreightRegion();
        $freight_region_where = ['template_id' => $this->goods['template_id'], 'region_id' => $this->regionId];
        $freightRegion = $FreightRegion->where($freight_region_where)->find();
        if(!empty($freightRegion)){
            return $freightRegion;
        }else{
            $parent_region_id = $this->getParentRegionList($this->regionId);
            $parent_freight_region_where = ['template_id' => $this->goods['template_id'], 'region_id' => ['IN',$parent_region_id]];
            $freightRegion = $FreightRegion->where($parent_freight_region_where)->order('region_id asc')->find();
            return $freightRegion;
        }
    }


    /**
     * 寻找Region_id的父级id
     * @param $cid
     * @return array
     */
    function getParentRegionList($cid){
        //$pids = '';
        $pids = array();
        $parent_id =  M('region')->cache(true)->where(array('id'=>$cid))->getField('parent_id');
        if($parent_id != 0){
            //$pids .= $parent_id;
            array_push($pids,$parent_id);
            $npids = $this->getParentRegionList($parent_id);
            if(!empty($npids)){
                //$pids .= ','.$npids;
                $pids = array_merge($pids,$npids);
            }

        }
        return $pids;
    }
}