<?php

namespace app\common\logic;

use think\Model;
use think\db;

/**
 * 自定义接口
 * Class Block
 * @package app\common\logic
 */
class Block extends Model
{

    /**
     * 商品列表板块参数设置
     * @param $data | ids 分类id|label 商品标签 | order 排序 | goods goods_ids
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function goods_list_block($data){

        if(!isset($data['num']) or empty($data['num']) or $data['num'] < 2){
            $count = 4;
        }else{
            $count = $data['num'];
        }

        $where['is_on_sale'] = 1;
        if($data['ids']){
            $ids_arr = explode(',', $data['ids']);
            foreach($ids_arr as $k=>$v){
                if(empty($v)) unset($ids_arr[$k]);
            }
            if($ids_arr){
                $where_cat['is_show'] = 1;
                $where_cat['parent_id'] = ['in', $ids_arr];
                $where_cat['level'] = 2; //查2级分类
                $ids_arr2 = Db::name('goods_category')->where($where_cat)->column('id');
                if($ids_arr2){
                    $ids_arr = array_merge($ids_arr, $ids_arr2);
                }

                $where_cat['parent_id'] = ['in', $ids_arr];
                $where_cat['level'] = 3; //查3级
                $ids_arr3 = Db::name('goods_category')->where($where_cat)->column('id');
                if($ids_arr3){
                    $ids_arr = array_merge($ids_arr, $ids_arr3);
                }

                $where['cat_id'] = ['in', $ids_arr];
            }
        }

        if($data['label']){
            $where[$data['label']] = 1;
        }
        if($data['min_price']){
            $where['min_price'] = ['egt', $data['min_price']];
        }
        if($data['max_price']){
            $where['max_price'] = ['lt', $data['max_price']];
        }
        if($data['goods']){
            $goods_id_arr = explode(',', $data['goods']);
            $where['goods_id'] = ['in', $goods_id_arr];
        }

        switch ($data['order']) {
            case '0':
                $order_str="sales_sum DESC";
                break;

            case '1':
                $order_str="sales_sum ASC";
                break;

            case '2':
                $order_str="shop_price DESC";
                break;

            case '3':
                $order_str="shop_price ASC";
                break;

            case '4':
                $order_str="last_update DESC";
                break;

            case '5':
                $order_str="last_update ASC";
                break;

            default:
                $order_str="sales_sum DESC";
                break;
        }

        $goodsList = Db::name('goods')->where($where)->order($order_str)->limit(0,$count)->select();
        $goodsList[0]['where'] = $where;
        return $goodsList;
    }
}