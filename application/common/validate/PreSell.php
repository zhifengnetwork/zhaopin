<?php
namespace app\common\validate;

use think\Validate;
use think\Db;

class PreSell extends Validate
{
    // 验证规则
    protected $rule = [
        'pre_sell_id'=>'checkId',
        'ladder_amount' => 'require|checkLadderAmount',
        'ladder_price' => 'require|checkLadderPrice',
        'sell_start_time' => 'require|checkSellStartTime',
        'sell_end_time' => 'require|checkSellEndTime',
        'deposit_price' => ['require', 'regex' => '([0-9]\d*(\.\d*[1-9])?)|(0\.\d*[1-9])','checkDepositPrice'],
        'stock_num' => 'require|number|gt:0|checkStockNum',
        'goods_id' => 'require|checkGoodsId',
        'item_id' => 'checkItemId',
        'pay_start_time' => 'checkPayStartTime',
        'pay_end_time' => 'checkPayEndTime',
        'title' => 'require|max:255',
        'delivery_time_desc' => 'require|max:255',
    ];
    //错误信息
    protected $message = [
        'ladder_amount.require' => '价格阶梯必须',
        'ladder_price.require' => '价格阶梯必须',
        'sell_start_time.require' => '活动开始时间必须',
        'sell_end_time.require' => '活动结束时间必须',
        'deposit_price.require' => '订金必须',
        'deposit_price.regex' => '订金格式错误',
        'stock_num.require' => '库存必须',
        'stock_num.number' => '库存必须为数字',
        'stock_num.gt' => '库存必须大于0',
        'goods_id.require' => '请选择参与预售的商品',
        'title.require' => '预售标题必须',
        'title.max' => '预售标题长度不得超过255字符',
        'delivery_time_desc.require' => '发货时间描述必须',
        'delivery_time_desc.max' => '发货时间描述长度不得超过255字符',
    ];

    /**
     * 检查活动时间
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkId($value, $rule)
    {
       $pre_sell = Db::name('pre_sell')->field('sell_start_time')->where('pre_sell_id', $value)->find();
       if(time() > $pre_sell['sell_start_time'] && $pre_sell['status'] == 1){
            return '预售活动已经开始不能更改预售商品';
       }
        return true;
    }


    /**
     * 检查活动时间
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkSellStartTime($value, $rule, $data)
    {
        $sell_start_time = strtotime($data['sell_start_time']);
        $sell_end_time = strtotime($data['sell_end_time']);
        if ($sell_start_time > $sell_end_time) {
            return '您输入了一个无效的时间，活动结束时间不能早于活动开始时间！';
        }
        return true;
    }
    /**
     * 检查活动时间
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkSellEndTime($value, $rule, $data)
    {
        $sell_end_time = strtotime($data['sell_end_time']);
        if($data['pre_sell_id'] > 0 && $data['deposit_price'] > 0 && !array_key_exists('pay_start_time', $data)){
            $pre_sell = Db::name('pre_sell')->where(['pre_sell_id'=>$data['pre_sell_id']])->find();
            if($sell_end_time > $pre_sell['pay_start_time']){
                return '尾款开始支付时间不能早于活动结束时间！';
            }
        }
        return true;
    }

    /**
     * 检查阶梯价格中的库存
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkLadderAmount($value, $rule, $data)
    {
        if ($data['stock_num'] < max($value)) {
            return '预定最多人数不能大于预售库存!';
        }
        if(min($value) <= 0){
            return  '预定人数不能小于零';
        }
        return true;

    }
    /**
     * 检查阶梯价格中的价格
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkLadderPrice($value, $rule)
    {
        if(min($value) <= 0){
            return  '阶梯价格不能小于0';
        }else{
            return true;
        }
    }
    /**
     * 检查商品id
     * @param $value |验证数据
     * @param $rule |验证规则
     * @return bool|string
     */
    protected function checkGoodsId($value, $rule)
    {
        $goods = Db::name('goods')->field('prom_type')->where('goods_id', $value)->find();
        if(empty($goods)){
            return '选择参与预售的商品不存在';
        }
        if($goods['prom_type'] != 4 && $goods['prom_type'] != 0){
            return '选择参与预售的商品已经参与了其他活动';
        }
        return true;
    }

    /**
     * 检查预售库存
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkStockNum($value, $rule, $data)
    {
        if($data['item_id']){
            $stock_num = Db::name('spec_goods_price')->where('item_id', $data['item_id'])->value('store_count');
        }else{
            $stock_num = Db::name('goods')->where('goods_id', $data['goods_id'])->value('store_count');
        }
        if($value > $stock_num){
            return '预售库存不得大于商品库存';
        }else{
            return true;
        }
    }

    protected function checkDepositPrice($value, $rule, $data){
        if ($value >= min($data['ladder_price'])) {
            return '定金不能大于等于阶梯价格！';
        }
        if($value > 0 && empty($data['pre_sell_id']) && (empty($data['pay_start_time']) || empty($data['pay_end_time']))){
            return '请选择尾款支付时间！';
        }
        return true;
    }
    /**
     * 检查支付尾款时间
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkPayStartTime($value, $rule, $data)
    {
        $pay_start_time = strtotime($data['pay_start_time']);
        $sell_start_time = strtotime($data['sell_start_time']);
        $sell_end_time = strtotime($data['sell_end_time']);
        if($sell_start_time > $pay_start_time){
            return '尾款开始支付时间不能早于活动开始时间！';
        }
        if($sell_end_time > $pay_start_time){
            return '尾款开始支付时间不能早于活动结束时间！';
        }
        return true;

    }
    /**
     * 检查支付尾款时间
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkPayEndTime($value, $rule, $data)
    {
        $pay_start_time = strtotime($data['pay_start_time']);
        $pay_end_time = strtotime($data['pay_end_time']);
        if ($pay_start_time > $pay_end_time) {
            return '尾款结束支付时间不能早于尾款开始支付时间！';
        }
        return true;
    }
    /**
     * 检查预售库存
     * @param $value |验证数据
     * @param $rule |验证规则
     * @param $data |全部数据
     * @return bool|string
     */
    protected function checkItemId($value, $rule, $data)
    {
        $spec_goods_price = Db::name('spec_goods_price')->field('prom_type,prom_id')->where('item_id', $value)->find();
        if(empty($spec_goods_price)){
            return '选择参与预售的商品规格不存在';
        }
        if($spec_goods_price['prom_type'] != 4 &&  $spec_goods_price['prom_type'] != 0){
            return '选择参与预售的商品规格已参与了其他活动';
        }
        if($data['pre_sell_id'] && $spec_goods_price['prom_id']){
            if($data['pre_sell_id'] != $spec_goods_price['prom_id']){
                return '选择参与预售的商品规格参与了其他预售活动';
            }
        }
        return true;
    }


}