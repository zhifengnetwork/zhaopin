<?php

namespace app\api\controller;
use think\Db;

class Groupon extends ApiBase
{
    /**
    * 拼团商品列表
    */
    public function goods_list(){
        //拼团专区图片
        $group_img = Db::table('category')->where('cat_name','like',"%拼团%")->value('img');
        
        $page = input('page');
        
        $where['gg.is_show'] = 1;
        $where['gg.is_delete'] = 0;
        $where['gg.status'] = 2;
        $where['g.is_del'] = 0;
        $where['g.is_show'] = 1;
        $where['gi.main'] = 1;

        $pageParam['query']['is_show'] = 1;
        $pageParam['query']['is_delete'] = 0;
        $pageParam['query']['status'] = 2;
        $pageParam['query']['is_del'] = 0;
        $pageParam['query']['main'] = 1;

        $list = Db::table('goods_groupon')->alias('gg')
                ->join('goods g','g.goods_id=gg.goods_id','LEFT')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->where($where)
                ->field('gg.groupon_id,g.goods_id,gg.target_number,gg.sold_number,gg.start_time,gg.end_time,gg.period,gg.sort,g.goods_name,g.desc,gi.picture img')
                ->paginate(10,false,$pageParam);
        $list = $list->all();
        
        if($list){
            foreach($list as $key=>&$value){
                //拿出拼团商品规格价格最低的来显示
                $value['price'] = Db::table('goods_sku')->where('goods_id',$value['goods_id'])->where('inventory','>',0)->min('groupon_price');

                $value['surplus'] = $value['target_number'] - $value['sold_number'];      //剩余量
                if($value['surplus']){
                    $value['surplus_percentage'] = $value['surplus'] / $value['target_number'];      //剩余百分比
                }else{
                    $value['surplus_percentage'] = 0;      //剩余百分比
                }

                //过期或者拼团人数已满，重新生成新团购信息
                if( !$value['surplus'] || $value['end_time'] < time()){
                    //更改团购过期状态
                    $update_res = Db::name('goods_groupon')->where('groupon_id',$value['groupon_id'])->update(['is_show'=>0,'status'=>3]);
                    if($update_res){
                        //生成新一期团购
                        $new_roupon = $this->new_groupon($value);
                        if ($new_roupon) $value = $new_roupon;
                    }
                }
            }
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['list'=>$list,'group_img'=>$group_img]]);
    }

    //生成新一期的团购商品
    public function new_groupon($groupon_data){
        if(isset($groupon_data['groupon_id'])) unset($groupon_data['groupon_id']);
        $groupon_data['sold_number'] = 0;
        $end_time = $groupon_data['end_time'] - $groupon_data['start_time'];
        $groupon_data['start_time'] = time();
        $groupon_data['end_time'] = $groupon_data['start_time'] + $end_time;
        $groupon_data['period'] = $groupon_data['period']+1;
        $goods_name = Db::name('goods')->where('goods_id',$groupon_data['goods_id'])->value('goods_name');
        $groupon_data['groupon_name'] = $goods_name . '_第'. $groupon_data['period'].'期' ;
        $groupon_data['sort'] = $groupon_data['sort'];
        $groupon_id = Db::name('goods_groupon')->strict(false)->insertGetId($groupon_data);
        if($groupon_id){
            $groupon_data['surplus'] = $groupon_data['target_number'];
            $groupon_data['surplus_percentage'] = 1;
            $groupon_data['groupon_id'] = $groupon_id;

            $redis = $this->getRedis();
            for($i=0;$i<$groupon_data['target_number'];$i++){
                $redis->rpush("GOODS_GROUP_{$groupon_id}",1);
            }

            return $groupon_data;
        }else{
            return false;
        }
    }
    
}
