<?php
namespace app\admin\controller;
use think\Db;

class Ajax
{
    public function get_goods(){
        $goods_name = input('goods_name');
        $res = Db::table('goods')->where('goods_name','like',"%{$goods_name}%")->field('goods_id,goods_name,price,cost_price')->select();
        jason($res);
    }

}
