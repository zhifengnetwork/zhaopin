<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/4/19
 * Time: 11:49
 */

namespace app\api\controller;
use app\common\model\Goods;
use think\Db;

class Shop extends ApiBase
{
    public function index () {
        dump(session('admin_user_auth'));die;
    }

    public function getShopData () {

        $res = model('DiyEweiShop')->getShopData();
        if (!empty($res)){
            return json(['code'=>1,'msg'=>'','data'=>$res]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据，请添加','data'=>'']);
        }
    }

    public function gooodsList () {
        $keyword = request()->param('keyword','');
        $cat_id = request()->param('cat_id',0,'intval');
        $page = request()->param('page',0,'intval');
        $goods = new Goods();
        $list = $goods->getGoodsList($keyword,$cat_id,$page);
        if (!empty($list)){
            return json(['code'=>1,'msg'=>'','data'=>$list]);
        }else{
            return json(['code'=>0,'msg'=>'没有数据哦','data'=>$list]);
        }
    }

    public function getGoodsData () {
        $goods_id = request()->param('goods_id',0,'intval');
        $data = model('Goods')->where('goods_id',$goods_id)->find();
        $sku =  Db::table('goods_sku')->where('goods_id',$data['goods_id'])->select();
        $data['sku'] = $sku;
        return json(['code'=>1,'msg'=>'','data'=>$data]);
    }
}