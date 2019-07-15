<?php
namespace app\api\model;
use think\Model;

class Goods extends Model
{
    protected $table = 'goods';

    public function goodsList($where = array(), $order = "", $limit = 20)
    {   
        $where['g.is_show'] = 1;
        $where['g.is_del'] = 0;

        $list = $this->alias('g')
            ->field('g.goods_id,g.goods_name,g.type_id,g.goods_attr,g.img,g.price,g.original_price,g.cat_id1,g.cat_id2,g.stock')
            ->field('c.cat_name')
            ->join('category c', 'c.cat_id in(g.cat_id1,g.cat_id2)', 'left')
            ->where($where)
            ->order($order)
            ->paginate($limit);

        return $list;
    }
}
