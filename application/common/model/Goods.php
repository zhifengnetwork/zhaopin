<?php
namespace app\common\model;

use think\helper\Time;
use think\Model;

class Goods extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    public function getGoodsList ($keyword = '',$cat_id = '',$page = 1) {
//        dump($_SERVER);die;
        $where = [];
        $where_cat = [];
        $where['a.is_show'] = 1;
        $where['a.is_del'] = 0;
        $where['b.main'] = 1;
        $field = 'a.goods_id,a.goods_name,a.desc,a.limited_start
        ,a.limited_end,a.goods_spec,a.price,a.original_price,a.stock,b.picture as img';
        if (!empty($keyword)){
            //商品搜索
            $where['a.goods_name'] = ['like','%'.str_replace(" ",'',$keyword).'%'];
        }
        if (!empty($cat_id)){
            $list = $this->alias('a')->join('goods_img b','b.goods_id = a.goods_id','left')
                ->where($where)->where(function ($query) use ($cat_id) {
                $query->where('a.cat_id1', $cat_id)->whereor('a.cat_id2', $cat_id);})
                ->field($field)->paginate(6,false,['page'=>$page]);
        }else{
            $list = $this->alias('a')->join('goods_img b','b.goods_id = a.goods_id','left')->where($where)->field($field)->paginate(6,false,['page'=> $page]);
        }
        return $list;
    }
}
