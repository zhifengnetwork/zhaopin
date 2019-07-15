<?php

namespace app\common\model;
use think\Db;
use think\Model;
class OrderGoods extends Model {

    protected $table='';

    //自定义初始化
    protected function initialize()
    {
        parent::initialize();
    }

    public function goods()
    {
        return $this->hasOne('goods','goods_id','goods_id');
    }
    public function getMemberGoodsPriceAttr($value, $data){
        if($data['prom_type'] == 4){
            return $data['goods_price'];
        }else{
            return $value;
        }
    }

    public function getTotalMemberGoodsPriceAttr($value, $data){
        if($data['prom_type'] == 4){
            return $data['goods_num'] * $data['goods_price'];
        }else{
            return $data['goods_num'] * $data['member_goods_price'];
        }
    }

    public function returnGoods()
    {
        return $this->hasOne('ReturnGoods', 'rec_id', 'rec_id');
    }
}
