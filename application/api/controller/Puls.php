<?php
/**
 * Created by PhpStorm.
 * User: MyPC
 * Date: 2019/5/28
 * Time: 14:14
 */

namespace app\api\controller;


use think\Db;

class Puls extends ApiBase
{
    public function puls_goods()
    {
        $where['is_show'] = 1;
        $where['is_del'] = 0;
        $where['is_puls'] = 1;
        $page = request()->param('page',0,'intval');
        $field = 'goods_id,goods_name,price,stock,number_sales,desc';
        $list = model('Goods')->where($where)->field($field)->paginate(2,'',['page'=>$page]);
        if (!empty($list)){
            foreach ($list as &$v){
                $v['picture'] = Db::table('goods_img')->where(['goods_id'=>$v['goods_id'],'main'=>1])->value('picture');
            }
        }
        return json(['code'=>1,'msg'=>'','data'=>$list]);
    }

}